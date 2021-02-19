"use strict";

module.exports = function (grunt) {
   // Load all Grunt tasks matching the `grunt-*` pattern
   require("load-grunt-tasks")(grunt);

   // Time how long tasks take. Can help when optimizing build times
   require("time-grunt")(grunt);

   grunt.initConfig({
      pkg: grunt.file.readJSON("package.json"),

      watch: {
         js: {
            files: ["js/src/**/*.js"],
            tasks: ["jshint"],
         },
         gruntfile: {
            files: ["Gruntfile.js"],
         },
         sass: {
            files: ["scss/**/*.scss"],
            tasks: ["sass", "autoprefixer"],
         },
         livereload: {
            options: {
               livereload: true,
            },
            files: [
               "design/**/*.css",
               "design/images/**/*",
               "js/**/*.js",
               "views/**/*.tpl",
            ],
         },
      },

      sass: {
         options: {
            sourceMap: true,
            outputStyle: "expanded",
         },
         dist: {
            files: [
               {
                  expand: true,
                  cwd: "scss/",
                  src: ["*.scss", "!_*.scss"],
                  dest: "design/",
                  ext: ".css",
               },
            ],
         },
      },

      autoprefixer: {
         options: {
            map: true,
            cascade: false,
         },
         dist: {
            src: ["design/admin.css", "design/style.css"],
         },
      },

      jshint: {
         options: {
            jshintrc: "js/.jshintrc",
         },
         all: ["js/src/**/*.js"],
      },
      wiredep: {
         dist: {
            src: ["scss/**/*.scss"],
         },
      },
   });

   grunt.registerTask("default", [, "sass", "autoprefixer", "jshint"]);
};
