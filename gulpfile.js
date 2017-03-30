var gulp = require( 'gulp' ),

    // gulp plugins

    del = require( 'del' ),
    concat = require( 'gulp-concat' ),
    gettext = require( 'gulp-gettext' ),
    replace = require( 'gulp-replace' ),

    // doesn't break pipe on error
    // so we don't need to restart gulp
    plumber = require( 'gulp-plumber' ),
    // get notification on error
    notify = require( 'gulp-notify' ),
    onError = function( error ) {
        notify.onError( {
            title:    'Gulp Failure :/',
            message:  '<%= error.message %>',
            sound:    'Beep'
        } )( error );

        this.emit( 'end' );
    },

    poFiles = ['./**/languages/*.po'];

/**
 * Compile .po files to .mo
 */
gulp.task( 'po2mo', function() {
    return gulp.src(poFiles)
        .pipe( gettext() )
        .pipe( gulp.dest( '.' ) )
} );

/**
 * Clear build/ folder.
 */
gulp.task( 'clear:build', function() {
    del.sync( 'build/**/*' );
} );

gulp.task( 'build', ['clear:build', 'po2mo'], function() {
    // collect all needed files
    gulp.src( [
        '**/*',
        // ... but:
        '!*.md',
        '!readme.txt',
        '!gulpfile.js',
        '!package.json',
        '!.gitignore',
        '!node_modules{,/**}',
        '!build{,/**}',
        '!assets{,/**}'
    ] ).pipe( gulp.dest( 'build/' ) );

    // concat files for WP's readme.txt
    // manually validate output with https://wordpress.org/plugins/about/validator/
    gulp.src( [ 'readme.txt', 'README.md', 'CHANGELOG.md' ] )
        .pipe( concat( 'readme.txt' ) )
        // remove screenshots
        // todo: scrennshot section for WP's readme.txt
        .pipe( replace( /\n\!\[image\]\([^)]+\)\n/g, '' ) )
        // WP markup
        .pipe( replace( /#\s*(Changelog)/g, "## $1" ) )
        .pipe( replace( /###\s*([^(\n)]+)/g, "=== $1 ===" ) )
        .pipe( replace( /##\s*([^(\n)]+)/g, "== $1 ==" ) )
        .pipe( replace( /==\s(Unreleased|[0-9\s\.-]+)\s==/g, "= $1 =" ) )
        .pipe( replace( /#\s*[^\n]+/g, "== Description ==" ) )
        .pipe( gulp.dest( 'build/' ) );
} );

/**
 * Watch tasks.
 *
 * Init watches by calling 'gulp' in terminal.
 */
gulp.task( 'default', function() {
    gulp.watch( poFiles, ['po2mo'] );
} );
