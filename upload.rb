require 'net/http'
require 'uri'
require 'listen'

module Minec
  extend self
  KEY = 'm1nK3y'
  def get_value(key)
    Net::HTTP.get_response(
      URI.parse("http://netile.se/minec.php?apkey=#{KEY}&a=get&k=#{key}")
    ).body
  end

  def send_data(name, data)
    response = Net::HTTP.post_form(
      URI.parse("http://netile.se/minec.php?apkey=#{KEY}&a=put_file&k=#{name}"),
      :file => data
    ).body

    puts response
  end

end

FILES = [
  'stage2.lua',
  'bootstrap.lua',
  'controller.lua',
  'lib.lua',
  'main.lua',
  'gui.lua',
  'monitor_main.lua',
  'monitor_stage2.lua']

puts "Starting listener"
# Listen to a single directory.
Listen.to(
  Dir.pwd,
  :filter => /\.lua$/,
  #:ignore => %r{ignored/path/},
  #:force_polling => true,
  :relative_paths => true
) do |modified, added, removed|
  puts "modified #{modified}"
  modified.each do |file|
    puts "Iterate over files?"
    if FILES.include? file
      puts "Will send: file #{file} to #{file.split('.')[0]}"
      Minec.send_data(file.split('.')[0],     File.read(file))
    end
  end
end

exit
Minec.send_data('stage2',     File.read('stage2.lua'))
Minec.send_data('bootstrap',  File.read('bootstrap.lua'))
Minec.send_data('controller', File.read('controller.lua'))
Minec.send_data('lib',        File.read('lib.lua'))
Minec.send_data('main',       File.read('main.lua'))