How to use Vanilla's SASS Plugins
=================================

Installing sass
---------------

1. Install sass with `sudo gem install sass`.
2. Install compass with `sudo gem install compass`.
3. Create a symlink to that points /Library/Ruby/Site/sass to this misc/sass directory.


Initializing a theme's design folder with compass.
--------------------------------------------------

1. Change directory to the design folder (`cd design`).
2. Type `compass init`. This will create a bunch of files.
3. Edit `config.rb` and make the following changes:
    * Add `require 'sass/sass-plugins'` at the top where it tells you to "Require any additional compass plugins here."
    * Change the `css_dir` to `css_dir = "."`. This will output the css files into the design folder.
4. Make sure you create a `sass/custom.scss` file.
5. You can now type `compass watch` from the design folder and your css files will be automatically generated when you edit the sass files.