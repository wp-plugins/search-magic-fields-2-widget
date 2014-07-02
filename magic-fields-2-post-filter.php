<?php

/*
    Copyright 2013  Magenta Cuda

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

trait Magic_Fields_2_Toolkit_Post_Filters {

    # find post ids of posts with specified taxonomy and tags

    static function get_posts_with_spec( $spec ) {
        global $wpdb;
        if ( preg_match( '#^((\d+)(,)?)+$#', $spec, $matches ) ) {
            # this is a list of ids so ...
            $includes = array_filter( explode( ',', $matches[0] ) );
            #error_log( '##### Magic_Fields_2_Toolkit_Post_Filters::get_posts_with_spec:$includes=' . print_r( $includes, TRUE ) );
            return $includes;
        }
        #if ( !preg_match( '/^(\w+)(:((((\w+):(((-)?\w+)(,)?)+)(;)?)+))?(#(\d+))?$/', $spec, $matches ) ) { return array(); };
        if ( !preg_match( '/^([a-z_-]+)(:(((([a-z_-]+):(((-)?[a-z_-]+)(,)?)+)(;)?)+))?(#(\d+))?$/', $spec, $matches ) ) { return array(); };
        # this is a post specifier of the form - post_type:taxonomy1:tag1,tag2,-tag3;taxonomy2:tag4,-tag5,tag6,tag7 ...
        #error_log( '##### Magic_Fields_2_Toolkit_Post_Filters::get_posts_with_spec:$matches=' . print_r( $matches, TRUE ) );
        $post_type = $matches[1];
        if ( !empty( $matches[13] ) ) { $limit = $matches[13]; }
        if ( empty( $matches[3] ) ) {
            return $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_type = '$post_type'" . ( $limit ? " LIMIT $limit" : '' ) );
        }
        # get tags by taxonomies
        $tax_matches = explode( ';', $matches[3] );
        #error_log( '##### Magic_Fields_2_Toolkit_Post_Filters::get_posts_with_spec:$tax_matches=' . print_r( $tax_matches, TRUE ) );
        $include_sqls = array();
        $exclude_sqls = array();
        foreach ( $tax_matches as $tax_match ) {
            # each iteration does a taxonomy
            if ( !$tax_match ) { continue; }
            list( $taxonomy, $tags ) = explode( ':', $tax_match );
            $tags = array_filter( explode( ',', $tags ) );
            #error_log( '##### Magic_Fields_2_Toolkit_Post_Filters::get_posts_with_spec:$taxonomy=' . $taxonomy );
            #error_log( '##### Magic_Fields_2_Toolkit_Post_Filters::get_posts_with_spec:$tags=' . print_r( $tags, TRUE ) );
            # extract exclude tags
            $exclude_tags = array_filter( $tags, function ( $value ) { return substr_compare( $value, '-', 0, 1 ) === 0; } );
            # subtract exclude tags to get include tags
            $include_tags = array_diff( $tags, $exclude_tags );
            # remove - from exclude tags
            $exclude_tags = array_map( function( $value ) { return substr( $value, 1 ); }, $exclude_tags );
            #error_log( '##### Magic_Fields_2_Toolkit_Post_Filters::get_posts_with_spec:$include_tags=' . print_r( $include_tags, TRUE ) );
            #error_log( '##### Magic_Fields_2_Toolkit_Post_Filters::get_posts_with_spec:$exclude_tags=' . print_r( $exclude_tags, TRUE ) );
            $include_exclude_tags = array( $include_tags, $exclude_tags );
            foreach ( $include_exclude_tags as &$tags ) {
                if ( !$tags ) { continue; }
                # construct include and exclude sql for each taxonomy in separate passes
                if ( $tags === $include_tags ) { $sqls =& $include_sqls; }
                else { $sqls =& $exclude_sqls; }
                $tag_sql = '';
                foreach ( $tags as $tag ) {
                    if ( !$tag ) { continue; }
                    if ( $tag_sql ) { $tag_sql .= ' OR '; }
                    $tag_sql .= " t.slug = '$tag' ";
                }
                if ( $tag_sql ) {
                    $sqls[] = " ( x.taxonomy = '$taxonomy' AND ( $tag_sql ) ) ";
                }
                unset( $sqls );
            }
        }   # foreach ( $tax_matches as $tax_match ) {
        $sql_prefix = <<<EOD
            SELECT DISTINCT r.object_id FROM $wpdb->term_relationships r, $wpdb->term_taxonomy x, $wpdb->terms t, $wpdb->posts p
                WHERE r.term_taxonomy_id = x.term_taxonomy_id AND x.term_id = t.term_id AND r.object_id = p.ID
                    AND p.post_type = '$post_type'
EOD;
        $includes = NULL;
        if ( $include_sqls ) {
            foreach ( $include_sqls as $include_sql ) {
                # get includes for this taxonomy
                $sql = $sql_prefix . " AND ( $include_sql ) ";
                #error_log( '##### Magic_Fields_2_Toolkit_Post_Filters::get_posts_with_spec:$include_sql=' . $sql );
                $new_includes = $wpdb->get_col( $sql );
                #error_log( '##### Magic_Fields_2_Toolkit_Post_Filters::get_posts_with_spec:$includes='
                #   . print_r( $new_includes, TRUE ) );
                if ( !$new_includes ) { return array(); }
                # intersect with includes of previous taxonomies
                if ( !$includes ) { $includes = $new_includes; }
                else { $includes = array_intersect( $includes, $new_includes ); }
            }
        } else {
            $includes = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_type = '$post_type'" );
        }
        if ( $exclude_sqls ) {
            $excludes = array();
            foreach ( $exclude_sqls as $exclude_sql ) {
                # get excludes for this taxonomy
                $sql = $sql_prefix . " AND ( $exclude_sql ) ";
                #error_log( '##### Magic_Fields_2_Toolkit_Post_Filters::get_posts_with_spec:$exclude sql=' . $sql );
                $new_excludes = $wpdb->get_col( $sql );
                #error_log( '##### Magic_Fields_2_Toolkit_Post_Filters::get_posts_with_spec:$excludes=' . print_r( $new_excludes, TRUE ) );
                $excludes = array_unique( array_merge( $excludes, $new_excludes ) );
            }
            if ( $excludes ) {
                # subtract excludes
                $includes = array_diff( $includes, $excludes );
            }
        }
        #error_log( '##### Magic_Fields_2_Toolkit_Post_Filters::get_posts_with_spec:$includes=' . print_r( $includes, TRUE ) );
        if ( $limit && count( $includes ) > $limit ) {
            # too many posts so ...
            $includes = array_slice( $includes, 0, $limit );
        }
        return $includes;
    }   # static function get_posts_with_spec( $spec ) {

    #get_posts_with_spec( 'carburetor:year:-1969,1970,-1971,1972;company_type:llc,-corporation,;' );
    
}   # trait Magic_Fields_2_Toolkit_Post_Filters {

