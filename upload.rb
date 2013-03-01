require 'net/http'
require 'uri'

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

Minec.send_data('bootstrap',  File.read('bootstrap.lua'))
Minec.send_data('controller', File.read('controller.lua'))
Minec.send_data('lib',        File.read('lib.lua'))
Minec.send_data('main',       File.read('main.lua'))