#!/usr/bin/env ruby2.6

require 'json'
require 'open3'
require 'optparse'
require 'yaml'

# Parse the options.
options = {}
OptionParser.new do |opts|
  opts.banner = 'Usage: acsf_large_scale_cron.rb [options]'

  opts.on('-c', '--concurrency=INT', 'Required. The number of sites to run cron simultaneously.') do |v|
    options[:concurrency] = v.to_i
  end
  opts.on('-t', '--site-cron-ttl=INT', 'Required. The number seconds a site cron may run for.') do |v|
    options[:site_cron_ttl] = v.to_i
  end
  opts.on('-m', '--max-runtime=INT', 'Required. The number of seconds in which every site must be handled.') do |v|
    options[:max_runtime] = v.to_i
  end
  opts.on('-s', '--sitegroup=STRING', 'Required. The name of the hosting sitegroup.') do |v|
    options[:sitegroup] = v
  end
  opts.on('-e', '--environment=STRING', 'Required. The name of the hosting environment.') do |v|
    options[:environment] = v
  end
  opts.on('-d', '--drush=STRING', 'Required. The drush executable to be used to run the cron commands.') do |v|
    options[:drush] = v
  end
  opts.on('--cron-command=STRING', 'Required. The drush command to run on the sites.') do |v|
    options[:cron_command] = v
  end
  opts.on('--domain-suffix=STRING', 'Specify the domain suffix which will be used to select domains to run the cron on.') do |v|
    options[:domain_suffix] = v
  end
  opts.on('--domain-filter=STRING', 'When this is set to "preferred" then only the preferred flagged domains will run the cron.') do |v|
    options[:domain_filter] = v
  end
  opts.on('-l', '--limit=INT', 'When provided, this will limit how many sites will be handled. When left out, every domain will be handled.') do |v|
    options[:limit] = v.to_i
  end
  opts.on('-p', '--page=INT', 'When the limit option is provided, the script partitions sites and this option will choose which partition to handle. The initial page number is 1.') do |v|
    options[:page] = v.to_i
  end
  opts.on('-h', '--heartbeat=INT', 'The number of seconds to sleep between checking on child processes. Defaults to 1 second.') do |v|
    options[:heartbeat] = v.to_i
  end

end.parse!

options[:heartbeat] = options[:heartbeat] || 1

# Validate that the required options are present.
mandatory = [:concurrency, :site_cron_ttl, :max_runtime, :sitegroup, :environment, :drush, :cron_command]
missing = mandatory.select{ |param| options[param].nil? }
unless missing.empty?
  raise OptionParser::MissingArgument.new(missing.join(', '))
end
# At the moment either the domain-filter or the domain-suffix option must be
# specified.
if (options[:domain_filter].nil? && options[:domain_suffix].nil?)
  raise "Either the domain-filter or the domain-suffix option must be provided."
end

def get_sites(hosting_sitegroup, hosting_environment, domain_suffix, domain_filter, limit, page)
  # Read the sites.json for the list of sites.
  sites_struct = JSON.parse(IO.read("/mnt/files/#{hosting_sitegroup}.#{hosting_environment}/files-private/sites.json"))
  # Gather the sites from the JSON struct, only take active sites.
  sites_registry = {}
  sites_struct['sites'].each do |key, data|
    unless data['flags'].empty?
      # Restricted sites usually mean that an installation or another process is
      # working on the site, so need to skip those.
      next if data['flags'].key?('access_restricted') && data['flags']['access_restricted'].key?('enabled') && data['flags']['access_restricted']['enabled'] == 1
      # The 'operation' key means that the site is in a usable state but a
      # process is using the site's data.
      next if data['flags'].key?('operation') && data['flags']['operation'] == 'move'
    end
    # Avoid picking up a site multiple times which could happen when using
    # site filtering with domain suffix.
    if (!sites_registry.has_key?(data['name']))
      if (!domain_suffix.nil?)
        # Crons are run on domains that are selected by suffix.
        if (key.end_with?(domain_suffix))
          sites_registry[data['name']] = key
        end
      elsif (!domain_filter.nil?)
        # Crons are run on the preferred domains only.
        if (!data['flags'].empty? && data['flags'].key?('preferred_domain') && data['flags']['preferred_domain'] == true)
          sites_registry[data['name']] = key
        end
      end
    end
  end
  sites = sites_registry.values()
  # Sort sites alphabetically.
  sites.sort_by! do |item|
    item
  end
  # Handle site list partitioning.
  if (limit)
    offset = page ? (page - 1) * limit : 0
    sites = sites.slice(offset, limit)
  end
  return sites
end


start_time = Time.now

all_cron_logs_directory = "/mnt/tmp/#{options[:sitegroup]}.#{options[:environment]}/logs"
# Make sure that the directory for the logs exist. The logs will be gathered
# under a directory designated by the current day.
`mkdir -p #{all_cron_logs_directory}/large_scale_cron_#{start_time.strftime('%Y%m%d')}`
log_file = "#{all_cron_logs_directory}/large_scale_cron_#{start_time.strftime('%Y%m%d')}/#{start_time.strftime('%H%M%S')}.log"

# Get the list of sites to run the cron on.
sites = get_sites(options[:sitegroup], options[:environment], options[:domain_suffix], options[:domain_filter], options[:limit], options[:page])

# Thread tracker, to see if we have reached the maximum concurrency or not.
threads = {}
# Cron command output temporary storage. Gets cleaned soon after a script has
# finished runing.
cron_runs = {}
# List of sites which finished running the cron scripts.
handled_sites = []
# Remaining sites is the same as the list of all sites at the start.
remaining_sites = sites

while true
  # Determine if there is still time to handle sites.
  runtime_exceeded = Time.now - start_time >= options[:max_runtime]

  # List of sites that are either waiting for the cron scripts to run on them or
  # are already running.
  remaining_sites = sites - handled_sites

  # If there is still time and there is a free thread, then handle another cron.
  if (!runtime_exceeded && threads.size < options[:concurrency] && !remaining_sites.empty?)
    # Pick the next batch of sites based on the free thread slots. Avoid picking
    # sites that are already being processed.
    sites_to_be_handled = remaining_sites - threads.keys
    sites_to_process = sites_to_be_handled.slice(0, options[:concurrency] - threads.size)

    # Start threads.
    sites_to_process.each do |site_domain|
      threads[site_domain] = Thread.new do
        process_start_time = Time.now.to_i
        command = "timeout #{options[:site_cron_ttl]}s #{options[:drush]} -r /var/www/html/#{options[:sitegroup]}.#{options[:environment]}/docroot -l https://#{site_domain} #{options[:cron_command]}"
        stdout, stderr, status = Open3.capture3 command

        cron_runs[site_domain] = {
          'pid' => status.pid,
          'exit_code' => status.exitstatus,
          'stdout' => stdout.strip,
          'stderr' => stderr.strip,
          'start_time' => process_start_time,
          'end_time' => Time.now.to_i,
        }
      end
    end
  end

  # Stop processing when all sites ran the cron command. If the allowed time has
  # been exceeded then wait for the last threads to finish, then quit.
  if (remaining_sites.empty? || (threads.empty? && runtime_exceeded))
    break
  end

  # Sleep a bit before checking the threads.
  if (options[:heartbeat] > 0)
    sleep(options[:heartbeat])
  end

  # Check if any of the threads have finished and free those slots up and log
  # the process.
  open(log_file, 'a') do |f|
    threads.each do |site_domain, thread|
      if (thread.status == false || thread.status == nil)
        handled_sites << site_domain
        f.puts({site_domain => cron_runs[site_domain]}.to_yaml)
        threads.delete(site_domain)
        cron_runs.delete(site_domain)
      end
    end
  end
end

# Check what sites did not get the cron runs.
if (!remaining_sites.empty?)
  open(log_file, 'a') do |f|
    remaining_sites.each do |site_domain|
      f.puts({site_domain => {'pid' => nil, 'exit_code' => nil, 'stdout' => nil, 'stderr' => nil, 'start_time' => nil, 'end_time' => nil}}.to_yaml)
    end
  end
end

# Log even if nothing was handled.
if (sites.empty?)
  open(log_file, 'a') do |f|
    f.puts({}.to_yaml)
  end
end

# If yesterday's logs have not yet been archived yet, then do it.
yesterday = Time.at(start_time.to_i - 86400)
yesterdays_log_directory_name = "large_scale_cron_#{yesterday.strftime('%Y%m%d')}"
# Check if there is a directory for yesterday, then archive it with tar (with
# the -C the tar will change directory to avoid having absolute paths in the
# archive) and finally delete the logs directory.
`[ -d #{all_cron_logs_directory}/#{yesterdays_log_directory_name} ] && tar -C #{all_cron_logs_directory} -cvzf #{all_cron_logs_directory}/#{yesterdays_log_directory_name}.tgz #{yesterdays_log_directory_name} && rm -rf #{all_cron_logs_directory}/#{yesterdays_log_directory_name}`

# Cleanup archives: anything older than 2 days should be deleted.
`find #{all_cron_logs_directory}/ -maxdepth 1 -name 'large_scale_cron_*' -mtime +2 -print0 | xargs -0 -I {} rm -rv {}`
