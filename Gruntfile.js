module.exports = function (grunt) {

    grunt.initConfig({
        concat: {
            frontend: {
                src: [
                    './bower_components/jquery/jquery.js',
                    './bower_components/dist/js/bootstrap.js',
                    './app/assets/javascript/frontend.js'
                ],
                dest: './public/static/javascript/frontend.js'
            },
            backend: {
                src: [
                    './bower_components/jquery/jquery.js',
                    './bower_components/dist/js/bootstrap.js',
                    './app/assets/javascript/backend.js'
                ],
                dest: './public/static/javascript/backend.js'
            }
        },
        uglify: {
            options: {
                mangle: false  // Use if you want the names of your functions and variables unchanged
            },
            frontend: {
                files: {
                    './public/static/javascript/frontend.min.js': './public/static/javascript/frontend.js'
                }
            },
            backend: {
                files: {
                    './public/static/javascript/backend.min.js': './public/static/javascript/backend.js'
                }
            }
        },
        less: {
            development: {
                options: {
                    compress: false
                },
                files: {
                    "./public/static/stylesheets/frontend.css": "./app/assets/stylesheets/frontend.less",
                    "./public/static/stylesheets/backend.css": "./app/assets/stylesheets/backend.less"
                }
            },
            production: {
                options: {
                    compress: true
                },
                files: {
                    "./public/static/stylesheets/frontend.min.css": "./app/assets/stylesheets/frontend.less",
                    "./public/static/stylesheets/backend.min.css": "./app/assets/stylesheets/backend.less"
                }
            }
        },
        watch: {
            js: {
                files: ['./app/assets/javascript/*.js', './app/assets/javascript/*/*.js'],
                tasks: ['concat', 'uglify'],
                options: {
                    livereload: true
                }
            },
            less: {
                files: ['./app/assets/stylesheets/*.less', './app/assets/stylesheets/*/*.less'],
                tasks: ['less:development'],
                options: {
                    livereload: true
                }
            }
        }
    });

    grunt.loadNpmTasks('grunt-contrib-concat');
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-contrib-less');

    grunt.registerTask('default', ['concat', 'uglify', 'less']);
};