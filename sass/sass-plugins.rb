require 'sass'
require 'sass/script/color'
require 'sass/script/string'

module Vanilla
	# returns a hex string without the # symbol
	def hex_str(color)
		assert_type color, :Color
		Sass::Script::String.new("#{color.send(:hex_str)[1..-1]}".downcase)
	end
end

module Sass::Script::Functions
	include Vanilla
end

# puts Sass::Script::Functions.hex_str(Sass::Script::Color.new([255,0,0]))