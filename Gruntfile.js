module.exports = function(grunt) {

  // Project configuration.
  grunt.initConfig({
    pkg: "cio4wc",
    uglify: {
      options: {
        banner: '/*! <%= pkg.name %> <%= grunt.template.today("yyyy-mm-dd") %> */\n',
	sourceMap: true,
	beautify:true,
	uglify: false
      },
      build: {
        src: 'assets/js/<%= pkg %>.js',
        dest: 'assets/js/<%= pkg %>.min.js'
      }
    }
  });

  // Load the plugin that provides the "uglify" task.
  grunt.loadNpmTasks('grunt-contrib-uglify');

  // Default task(s).
  grunt.registerTask('default', ['uglify']);

};
