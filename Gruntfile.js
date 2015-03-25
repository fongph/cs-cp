module.exports = function (grunt) {

    grunt.initConfig({
        concat: {
            //boower-bad-library: daterangepicker
            frontend: {
                src: [
                    './bower_components/jquery/dist/jquery.js',
                    './bower_components/bootstrap/dist/js/bootstrap.js', // @TODO: replace for include files from "js" directory
                    './app/assets/javascript/main.js',
                    './app/assets/javascript/frontend.js'
                ],
                dest: './public/static/javascript/frontend.js'
            },
            backend: {
                src: [
                    './bower_components/jquery/dist/jquery.js',
                    './bower_components/bootstrap/dist/js/bootstrap.js', // @TODO: replace for include files from "js" directory
                    './bower_components/moment/moment.js',
                    './bower_components/underscore/underscore.js',
                    './bower_components/jquery.maskedinput/src/jquery.maskedinput.js',
                    './app/assets/javascript/deprecated/jquery.dataTables.js',
                    './app/assets/javascript/deprecated/daterangepicker.js',
                    //'./bower_components/bootstrap-daterangepicker/daterangepicker.js',
                    './app/assets/javascript/main.js',
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
        // @TODO: add awesome fonts copy task
    });

    grunt.loadNpmTasks('grunt-contrib-concat');
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-contrib-less');

    grunt.registerTask('default', ['concat', 'uglify', 'less']);
};