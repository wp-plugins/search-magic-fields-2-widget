<?php

/*
 * Description:   Shortcode for showing Magic Fields 2 custom fields, custom 
 *                groups and custom taxonomies.
 * Documentation: http://magicfields17.wordpress.com/magic-fields-2-toolkit-0-4/#shortcode
 * Author:        Magenta Cuda
 * License:       GPL2
 */

/*  Copyright 2013  Magenta Cuda

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

#error_log( '__FILE__ =' . __FILE__ );

include_once( dirname( __FILE__ ) . '/magic-fields-2-group-key.php' );
include_once( dirname( __FILE__ ) . '/magic-fields-2-post-filter.php' );

class Magic_Fields_2_Toolkit_Dumb_Shortcodes {
    use Magic_Fields_2_Toolkit_Post_Filters;
	public static $recursion_separator = '>';
    public function __construct() {
        # wrapper for the individual values of a field
        $wrap_value = function( $value, $field, $type, $filters, $before, $after, $separator, $classes = array(),
            $group_index = 0, $field_index = 0, $post_id = 0 ) {
            if ( $filters !== NULL ) {
                foreach( explode ( ';', $filters ) as $filter) {
                    if ( function_exists( $filter ) ) {
                        $value = call_user_func( $filter, $value, $field, $type, $classes, $group_index, $field_index, $post_id );
                    }
                }
            }
            if ( $value === NULL || $value === '' || $value === FALSE ) { return ''; }
            return $before . $value . $after . $separator;
        };
        # wrapper for a field 
        $wrap_field_value = function( $value, $before, $after, $separator, $label, $field, $class, $field_rename, $path ) {
            if ( function_exists( $field_rename ) ) {
                list( $label, $field ) = call_user_func( $field_rename, $label, $field, $path );
            } else {
                $label = trim( $path . self::$recursion_separator . $label, self::$recursion_separator );
            }
            $label = str_replace( ' ', '&nbsp;', $label );
            return str_replace( '<!--$class-->', $class, str_replace( '<!--$Field-->', $label,
				str_replace( '<!--$field-->', $field, $before ) ) ). $value
				. str_replace( '<!--$class-->', $class, str_replace( '<!--$Field-->', $label,
				str_replace( '<!--$field-->', $field, $after ) ) ) . $separator;
        };
        # wrapper for a group of fields
		$wrap_group_value = function( $value, $before, $after, $separator, $label, $group, $index, $class, $rename, $path ) {
			if ( !$label ) { $label = $group; }
            if ( function_exists( $rename ) ) {
                list( $label, $group ) = call_user_func( $rename, $label, $group, $path );
            } else {
                $label = trim( $path . self::$recursion_separator . $label, self::$recursion_separator );
            }
            $label = str_replace( ' ', '&nbsp;', $label );
            return str_replace( '<!--$class-->', $class, str_replace( '<!--$Group-->', $label,
				str_replace( '<!--$group-->', $group, $before ) ) ). $value
				. str_replace( '<!--$class-->', $class, str_replace( '<!--$Group-->', $label,
				str_replace( '<!--$group-->', $group, $after ) ) ) . $separator;
		};
        $show_custom_field = function( $post_id, $the_names, $before, $after, $separator, $filter, $field_before,
            $field_after, $field_separator, $field_rename, $group_before, $group_after, $group_separator, $group_rename,
			$multi_before, $multi_after, $multi_separator, $finals, $path, $parent_ids = array() )
            use ( &$show_custom_field, $wrap_value, $wrap_field_value, $wrap_group_value ) {
            global $wpdb;
            $content = '';
            $the_fields = $the_names;
            if ( !substr_compare( $the_fields, '{', 0, 1 ) ) { $the_fields = trim( $the_fields, '{}' ); }
            # field parameter is of the form: field_specifier1;field_specifier2;field_specifier3 ...
            preg_match_all( '/(([^{};]+)(\.{[^{}]+})?)(;|$)/', $the_fields, $fields );
            $the_fields = $fields[1];
            #error_log( '##### $show_custom_field():$the_fields=' . print_r( $the_fields, TRUE ) );
            foreach ( $the_fields as $the_name ) {
                # do one field specifier of a field parameter of the form: field_specifier1;field_specifier2;field_specifier3 ...
                # first separate field specifier into path components
                $names = explode( '.', $the_name, 2 );
                # do first path component
                # because the wordpress editor seems to insert noise spaces trim the component 
                $field = trim( $names[0] );
                if ( !preg_match( '/((\*_\*)|([\w-]+(\*)?))(<(\*|[\w\s]+)((,|><)(\*|\d+))?>)?(g|f)?(:((\*?-?[a-zA-Z0-9_]+),?)+)?/',
					$field, $matches ) || $matches[0] != $field ) {
					#error_log( '##### $show_custom_field:$matches=' . print_r( $matches, true ) );
					return '<div style="border:2px solid red;color:red;padding:5px;">'
						. "\"$field\" is an invalid field expression for short code: show_custom_field.</div>";
				}
                #error_log( '##### $show_custom_field:$matches=' . print_r( $matches, true ) );
                if ( array_key_exists( 1, $matches ) ) { $the_field = $matches[1]; }
                else { return '#ERROR#'; }
                if ( array_key_exists( 6, $matches ) ) { $the_group_index = $matches[6]; }
                else { $the_group_index = 1; }
                if ( array_key_exists( 9, $matches ) ) { $the_field_index = $matches[9]; }
                else { $the_field_index = 1; }
                $fields_by_group = ( array_key_exists( 10, $matches ) && $matches[10] == 'f' ) ? FALSE : TRUE;
                #error_log( '##### $show_custom_field:$fields_by_group=' . $fields_by_group );
				$the_group_excludes = array();
				$the_group_classes = array();
				$the_excludes = array();
				$the_classes = array();
                if ( array_key_exists( 11, $matches ) ) {
					$raw_classes = explode( ',', substr( $matches[11], 1 ) );
					foreach ( $raw_classes as $raw_class ) {
						if (        substr_compare( $raw_class, '*-', 0, 2 ) === 0 ) {
							$the_group_excludes[] = substr( $raw_class, 2 );
						} else if ( substr_compare( $raw_class, '*',  0, 1 ) === 0 ) {
							$the_group_classes[]  = substr( $raw_class, 1 );
						} else if ( substr_compare( $raw_class, '-',  0, 1 ) === 0 ) {
							$the_excludes[]       = substr( $raw_class, 1 );
						} else {
							$the_classes[]        = $raw_class;
						}
					}
					#error_log( '##### $the_group_excludes=' . print_r( $the_group_excludes, TRUE ) );
					#error_log( '##### $the_group_classes='  . print_r( $the_group_classes,  TRUE ) );
					#error_log( '##### $the_excludes='       . print_r( $the_excludes,       TRUE ) );
					#error_log( '##### $the_classes='        . print_r( $the_classes,        TRUE ) );
                } else {
                    $the_classes = NULL;
                }
                #error_log( '##### $show_custom_field():$the_field=' . $the_field . ', $the_classes='
                #    . ( is_array( $the_classes ) ? implode( ', ', $the_classes ) : 'NULL' ) );
                #error_log( "\$show_custom_field:{$the_field}[{$the_group_index}][{$the_field_index}]" );
				$all_group_names = $wpdb->get_col( 'SELECT name FROM ' . MF_TABLE_CUSTOM_GROUPS . ' WHERE post_type = "'
					. get_post_type( $post_id ) . '"' );
                #error_log( '##### $show_custom_field():post_type=' . get_post_type( $post_id ) );
                #error_log( '##### $show_custom_field():$all_group_names=' . print_r( $all_group_names, TRUE ) );
				if ( $the_field == '*_*' ) {
					$group_names = $all_group_names;
                } else if ( substr_compare( $the_field, '__default_', 0, 10 ) === 0 ) {
                    $group_names = array( '__default' );
				} else {
					$group_names = explode( '_', $the_field, 2 );   # group names should not use underscores
					if ( count( $group_names ) == 2 ) {
						array_pop( $group_names );
						if ( !in_array( $group_names[0], $all_group_names ) ) {
							$group_names = array( '__default' );
						}
					} else { 
						$group_names = array( '__default' );
					}
				}
                #error_log( '##### $show_custom_field():$group_names=' . print_r( $group_names, TRUE ) );
				$the_field0 = $the_field;
				foreach ( $group_names as $group_name ) {
					$mf2tk_key_name = ( $group_name != '__default' ? $group_name . '_' : '' ) . 'mf2tk_key';
					if ( $the_field0 == '*_*' ) { $the_field = $group_name . '_*'; }
					$not_magic_field = FALSE;
					if ( substr_compare( $the_field, '_*', -2, 2 ) === 0 ) {
						$the_group = substr( $the_field, 0, strlen( $the_field ) - 2 );
						$the_field_data = $wpdb->get_results( 'SELECT cf.name, cf.label, cf.description, cf.type FROM '
							. MF_TABLE_CUSTOM_FIELDS . ' cf INNER JOIN ' . MF_TABLE_CUSTOM_GROUPS . ' cg WHERE cg.name = "'
							. $the_group . '" AND cg.post_type = "' . get_post_type( $post_id )
							. '" AND cf.custom_group_id = cg.id' . ' ORDER BY cf.display_order', OBJECT_K );
						if( !$the_field_data ) { continue; }
						#error_log( 'results=' . print_r( $results, TRUE ) );
						$fields = array_map( function( $row ) { return $row->label; }, $the_field_data );
						if ( array_key_exists( $mf2tk_key_name, $fields ) ) { unset( $fields[$mf2tk_key_name] ); }
						#error_log( '##### $show_custom_field():fields=' . print_r( $fields, TRUE ) );
					} else {
						$the_field_data = $wpdb->get_results( 'SELECT name, label, description, type FROM '
							. MF_TABLE_CUSTOM_FIELDS . " WHERE name IN ( '$the_field', '$mf2tk_key_name' ) AND post_type = '"
							. get_post_type( $post_id ) . '\'', OBJECT_K );
						#error_log( 'column=' . print_r( $column, TRUE ) );
						if ( $the_field_data && isset( $the_field_data[$the_field] ) ) {
							$fields = array( $the_field => $the_field_data[$the_field]->label );
						} else {
							$fields = array( $the_field => $the_field );
							$not_magic_field = TRUE;
						}
						#error_log( '##### $show_custom_field():fields=' . print_r( $fields, TRUE ) );
					}
					if ( $mf2tk_key_data = (array) $the_field_data[$mf2tk_key_name] ) {
						preg_match( '/\[\*([a-zA-Z0-9_]+,?)+\*\]/', $mf2tk_key_data['description'], $mf2tk_key_classes );
						if ( $mf2tk_key_classes ) { $mf2tk_key_classes = explode( ',', trim( $mf2tk_key_classes[0], '[]*' ) ); }
					}
					if ( $the_group_classes
						&& ( !$mf2tk_key_classes || !array_intersect( $the_group_classes, $mf2tk_key_classes ) ) ) {
						continue;
					}
					if ( $the_group_excludes
						&& ( $mf2tk_key_classes && array_intersect( $the_group_excludes, $mf2tk_key_classes ) ) ) {
						continue;
					}
					#error_log( '##### $the_group_index=' . $the_group_index );
					if ( $the_group_index === '*' ) {
						$group_indices = get_order_group( key( $fields ), $post_id );
					} else if ( !is_numeric( $the_group_index ) ) {
						if ( function_exists( '_get_group_index_for_key' ) ) {
							$group_indices = array( _get_group_index_for_key( $the_group, $the_field, $the_group_index ) );
						} else {
							$group_indices = array( -1 );
						}
					} else {
						$group_indices = array( $the_group_index );
					}
					#####
					$fields1 = $fields;
                    # outer field loop
					foreach ( $fields1 as $field1 => $label1 ) {
						$skip_field1 = FALSE;
						$groups_value = '';
						foreach ( $group_indices as $group_index ) {
							$mf2tk_key_value = get( $mf2tk_key_name, $group_index, 1, $post_id );
							$fields_value = '';
                            # inner field loop
							foreach ( $fields as $field2 => $label2 ) {
                                # use outer or inner field loop depending on order mode
								if ( $fields_by_group ) {
									$field = $field2;
									$label = $label2;
								} else {
									$field = $field1;
									$label = $label1;
								}
								#error_log( '##### $show_custom_field():$field=' . $field );
								$field_value = '';
								$recursion = FALSE;
								$skip_field2 = FALSE;
								if ( $not_magic_field ) {
									if ( !$the_classes ) {
										if ( $field == '__parent' ) {
                                            # handle the psuedo field __parent
											if ( array_key_exists( 1, $names ) ) {
												#error_log( '##### $show_custom_field():$names=' . print_r( $names, TRUE ) );
												$parent_ids1 = $parent_ids;
												$parent_id = array_pop( $parent_ids1 );
												$label = $wpdb->get_var( 'SELECT name FROM ' . MF_TABLE_POSTTYPES . ' WHERE type ="'
													. get_post_type( $parent_id ) . '"' );
												$field_value .= $show_custom_field( $parent_id, $names[1], $before, $after,
													$separator, $filter, $field_before, $field_after, $field_separator,
													$field_rename, $group_before, $group_after, $group_separator, $group_rename,
													$multi_before, $multi_after, $multi_separator, NULL,
													$path . self::$recursion_separator . $label, $parent_ids1 ) . $field_separator;
												$recursion = TRUE;
											} else {
												$field_value .= $wrap_value( end( $parent_ids ), $field, 'related_type', $filter, $before,
													$after, $separator );
												reset( $parent_ids );
											}
                                        } else if ( $field == '__post_title' ) {
                                            # handle the psuedo field __post_title as a related_type if url_to_link is available
                                            $url_to_link_available = in_array( 'url_to_link', explode( ';', $filter ) );
                                            $field_value .= $wrap_value( ( $url_to_link_available ? $post_id
                                                : get_the_title( $post_id ) ), $field, ( $url_to_link_available ? 'related_type'
                                                : 'textbox' ), $filter, $before, $after, $separator );
                                            $label = "Post";
                                        } else if ( $field == '__post_author' ) {
                                            # handle the psuedo field __post_author which may be linkable
                                            $author = $wpdb->get_results( <<<EOD
                                                SELECT u.ID, u.display_name FROM $wpdb->users u, $wpdb->posts p
                                                    WHERE p.ID = "$post_id" AND u.ID = p.post_author
EOD
                                                , OBJECT );
                                            # TODO author id and display name
                                            $url_to_link_available = in_array( 'url_to_link', explode( ';', $filter ) );
                                            $field_value .= $wrap_value( ( $url_to_link_available ? $author[0]->ID
                                                : $author[0]->display_name ), $field, ( $url_to_link_available ? 'author'
                                                : 'textbox' ), $filter, $before, $after, $separator );
                                            $label = "Author";
										} else if ( ( $terms = get_the_terms( $post_id, $field ) ) && is_array( $terms ) ) {
											foreach ( wp_list_pluck( $terms, 'name' ) as $term ) {
												$field_value .= $wrap_value( $term, $field, 'taxonomy', $filter, $before, $after,
													$separator );
											}
											$column = $wpdb->get_col( 'SELECT name FROM ' . MF_TABLE_CUSTOM_TAXONOMY
												. ' WHERE type = "' . $field . '"' );
											if ( array_key_exists( 0, $column ) ) { $label = $column[0]; }
										} else {
											$values = get_post_custom_values( $field, $post_id );
											#error_log( '##### get_post_custom_values()=' . print_r( $values, TRUE ) );
											if ( is_array( $values ) ) {
												foreach ( $values as $value ) {
													if ( is_object( $value ) || is_array( $value ) ) { $value = serialize( $value ); }
													$field_value .= $wrap_value( $value, $field, NULL, $filter, $before, $after,
														$separator );
												}
											}
										}
									}
								} else {						
									if ( $the_field_index === '*' ) {
										$field_indices = get_order_field( $field, $group_index, $post_id );
										if ( !$field_indices ) { $field_indices = array( 1 ); }
									} else {
										$field_indices = array( $the_field_index );
									}
									#error_log('#####' . $field . '<' . $group_index . ',' . $the_field_index . '> $field_indices='
									#	. print_r( $field_indices, TRUE ) );
									foreach ( $field_indices as $field_index ) {
										$data = (array) $the_field_data[$field];
										#$data = get_data( $field, $group_index, $field_index, $post_id );
										#error_log( '$field=' . $field . ', $data=' . print_r( $data, TRUE ) );
										#error_log( '##### $field=' . $field . ', $post_id=' . $post_id
										#    . ', $data[\'type\']=' . $data['type']
										#    . ', $data[\'description\']=' . $data['description'] );
										#error_log( '##### $field=' . $field . ', $post_id=' . $post_id
										#    . ', $datax[\'type\']=' . $datax['type']
										#    . ', $datax[\'description\']=' . $datax['description'] );
                                        $value = get( $field, $group_index, $field_index, $post_id );
										#error_log( '##### $value=' . $value );
										#error_log( '##### $field=' . $field . ', $data[\'description\']=' . $data['description'] );
										preg_match( '/\[\*([a-zA-Z0-9_]+,?)+\*\]/', $data['description'], $classes );
										if ( $classes ) { $classes = explode( ',', trim( $classes[0], '[]*' ) ); }
										#error_log( '##### $show_custom_field():$field=' . $field . ', $classes='
										#    . implode( ', ', $classes ) );
										if ( $the_classes && ( !$classes || !array_intersect( $the_classes, $classes ) ) ) {
											$skip_field1 = $skip_field2 = TRUE;
											continue;
										}
										if ( $the_excludes && ( $classes && array_intersect( $the_excludes, $classes ) ) ) {
											$skip_field1 = $skip_field2 = TRUE;
											continue;
										}
										if ( $data['type'] === 'related_type' || $data['type'] === 'alt_related_type' ) {
											if ( $value ) {
												if ( is_array( $value ) ) { $values = $value; }
												else { $values = array( 0 => $value ); }
												foreach ( $values as $value ) {
													if ( $value ) {
														if ( array_key_exists( 1, $names ) ) {
															#error_log( '##### $show_custom_field():$names=' . print_r( $names, TRUE ) );
															$parent_ids1 = $parent_ids;
															array_push( $parent_ids1, $post_id );
															$field_value .= $show_custom_field( $value, $names[1], $before, $after,
																$separator, $filter, $field_before, $field_after, $field_separator,
																$field_rename, $group_before, $group_after, $group_separator,
																$group_rename, $multi_before, $multi_after, $multi_separator, NULL,
																$path . self::$recursion_separator . $label, $parent_ids1 )
																. $field_separator;
															$recursion = TRUE;
														} else {
															$field_value .= $wrap_value( $value, $field, $data['type'], $filter, $before,
																	$after, $separator, $classes );
														}
													}
												}
											}
										} else {
											if ( is_array( $value ) ) {
												if ( $value ) {
													$multi_value = '';
													foreach ( $value as $the_value ) {                            
														$multi_value .= $wrap_value( $the_value, $field, $data['type'], $filter,
															$multi_before, $multi_after, $multi_separator, $classes );
													}
													if ( $multi_separator && substr( $multi_value, strlen( $multi_value )
															- strlen( $multi_separator ) ) === $multi_separator ) {
														$multi_value = substr( $multi_value, 0, strlen( $multi_value )
															- strlen( $multi_separator ) );
													}
													#error_log( '$multi_value=' . $multi_value );
													$field_value .= $wrap_value( $multi_value, NULL, NULL, NULL, $before, $after,
														$separator, $classes );
												}
											} else {
												$field_value .= $wrap_value( $value, $field, $data['type'], $filter, $before, $after,
													$separator, $classes, $group_index, $field_index, $post_id );
											}
										}
										#error_log( '##### $show_custom_field:$field_value="' . $field_value . '"' );
									} # foreach ( $field_indices as $field_index ) { # results in $field_value
								}
                                # if using outer field loop do only one iteration on inner loop
								if ( !$fields_by_group ) { break; }
								if ( $skip_field2 ) { continue; }
								if ( !$recursion ) {
									if ( $separator
										&& substr( $field_value, strlen( $field_value ) - strlen( $separator ) ) === $separator ) {
										$field_value = substr( $field_value, 0, strlen( $field_value ) - strlen( $separator ) );
									}
									$fields_value .= $wrap_field_value( $field_value, $field_before, $field_after, $field_separator,
										$label, $field, is_array( $classes ) ? implode( ' ', $classes ) : '', $field_rename, $path );
                                    #error_log( '##### $show_custom_field:$fields_value="' . $fields_value . '"' );
								} else {
									$fields_value .= $field_value;
								}
								#error_log( '##### $show_custom_field:$fields_value="' . $fields_value . '"' );
							} # foreach ( $fields as $field2 => $label2 ) { # results in $fields_value
							if ( $fields_by_group ) {
								if ( $field_separator && substr( $fields_value, strlen( $fields_value ) - strlen( $field_separator ) )
									=== $field_separator ) {
									$fields_value = substr( $fields_value, 0, strlen( $fields_value ) - strlen( $field_separator ) );
								}
								$content .= $wrap_group_value( $fields_value, $group_before, $group_after, $group_separator,
									$mf2tk_key_value, "$group_name-$group_index", $group_index,
									is_array( $mf2tk_key_classes ) ? implode( ' ', $mf2tk_key_classes ) : '', $group_rename, $path );
							} else {
								if ( $skip_field1 ) { break; }
								if ( !$recursion ) {
									if ( $separator && substr_compare( $field_value, $separator, strlen( $field_value )
										- strlen( $separator ) ) === 0 ) {
										$field_value = substr( $field_value, 0, strlen( $field_value ) - strlen( $separator ) );
									}
									$groups_value .= $wrap_group_value( $field_value, $group_before, $group_after, $group_separator,
										$mf2tk_key_value, "$group_name-$group_index", $group_index,
										is_array( $mf2tk_key_classes ) ? implode( ' ', $mf2tk_key_classes ) : '', $group_rename,
										$path );
								} else {
									$groups_value .= $field_value;
								}
							}
						} # foreach ( $group_indices as $group_index ) { # results in $content or $groups_value
						if ( $fields_by_group ) {
							if ( $group_separator && substr_compare( $content, $group_separator, strlen( $content )
								- strlen( $group_separator ) ) === 0 ) {
								$content = substr( $content, 0, strlen( $content ) - strlen( $group_separator ) );
							}
                            # if using inner field loop do outer field loop only once
							break;
						}
						if ( $skip_field1 ) { continue; }
						if ( $group_separator && substr_compare( $groups_value, $group_separator, strlen( $groups_value )
							- strlen( $group_separator ) ) === 0 ) {
							$groups_value = substr( $groups_value, 0, strlen( $group_value ) - strlen( $group_separator ) );
						}
						if ( !$recursion ) {
							$content .= $wrap_field_value( $groups_value, $field_before, $field_after, $field_separator,
								$label, $field, is_array( $classes ) ? implode( ' ', $classes ) : '', $field_rename, $path );
						} else {
							$content .= $groups_value;
						}
					} # foreach ( $fields1 as $field1 => $label1 ) { # results in $content
				} # foreach ( $group_names as $group_name ) {
                $content .= $field_separator;
            } # foreach ( $the_fields as $the_name ) {
            # remove trailing field separator
            if ( $field_separator && substr_compare( $content, $field_separator, -strlen( $field_separator ),
                strlen( $field_separator ) ) === 0 ) {
                $content = substr( $content, 0, - strlen( $field_separator ) );
            }
            if ( $finals !== NULL ) {
                foreach( explode( ';', $finals ) as $final ) {
                    $content = call_user_func( $final, $content, $the_names );
                }
            }
            #error_log( '$content="' . $content . '"' );
            return $content;
        };

        add_shortcode( 'show_custom_field', function( $atts ) use ( &$show_custom_field ) {
            global $post;
            #error_log( '$atts=' . print_r( $atts, TRUE ) );
            extract( shortcode_atts( array(
                'field' => 'something',
                'before' => '',
                'after' => '',
                'separator' => '',
                'filter' => NULL,
                'multi_before' => NULL,
                'multi_after' => NULL,
                'multi_separator' => NULL,
                'field_before' => '',
                'field_after' => '',
                'field_separator' => '',
                'field_rename' => '',
                'group_before' => '',
                'group_after' => '',
                'group_separator' => '',
                'group_rename' => '',
                'final' => NULL,
                'post_id' => NULL,
                'post_before' => '',
                'post_after' => ''
            ), $atts ) );
            if ( $post_id === NULL ) { $post_id = $post->ID; }
            if ( $multi_before === NULL ) { $multi_before = $before; }
            if ( $multi_after === NULL ) { $multi_after = $after; }
            if ( $multi_separator === NULL ) { $multi_separator = $separator; }
            #error_log( '##### show_custom_field:' . print_r( compact( 'field', 'before', 'after', 'filter', 'separator',
            #    'field_before', 'field_after', 'field_separator', 'post_id' ), TRUE ) );
            if ( is_numeric( $post_id) ) {
                # single numeric post id
                $rtn = '';
                if ( $post_before ) { $rtn .= $post_before; }
                $rtn .= $show_custom_field( $post_id, $field, $before, $after, $separator, $filter, $field_before, $field_after,
					$field_separator, $field_rename, $group_before, $group_after, $group_separator, $group_rename, $multi_before,
					$multi_after, $multi_separator, $final, '' );
                if ( $post_after ) { $rtn .= $post_after; }
                #error_log( '##### show_custom_field:$rtn=' . $rtn );
                return $rtn;
            } else {
                # handle multiple posts
                # first get list of post ids
                $post_ids = Magic_Fields_2_Toolkit_Dumb_Shortcodes::get_posts_with_spec( $post_id );
                $rtn = '';
                foreach ( $post_ids as $post_id ) {
                    # do each post accumulating the output in $rtn
                    #error_log( '##### show_custom_field:$post_id=' . $post_id );
                    if ( $post_before ) { $rtn .= $post_before; }
                    $rtn .= $show_custom_field( $post_id, $field, $before, $after, $separator, $filter, $field_before, $field_after,
                        $field_separator, $field_rename, $group_before, $group_after, $group_separator, $group_rename, $multi_before,
                        $multi_after, $multi_separator, $final, '' );
                    if ( $post_after ) { $rtn .= $post_after; }
                }
                #error_log( '##### show_custom_field:$rtn=' . $rtn );
                return $rtn;
            }
        } );
        remove_filter( 'the_content', 'wpautop' );
    }
}   

new Magic_Fields_2_Toolkit_Dumb_Shortcodes();

# url_to_link() is a filter that wraps a linkable value with an <a> html element

function url_to_link( $value, $field, $type ) {
    global $wpdb;
    if ( ( $type === 'related_type' || $type === 'alt_related_type' ) && is_numeric( $value ) ) {
        $value = '<a href="' . get_permalink( $value ) . '">' . get_the_title ( $value ) . '</a>';
    } else if ( ( $type === 'image' || $type === 'image_media' ) && is_string( $value ) && strpos( $value, 'http' ) === 0 ) {
        $value = '<a href="' . $value . '">' . $value . '</a>';
    } else if ( $type === 'author' ) {
        $author = $wpdb->get_results( "SELECT u.display_name, u.user_url FROM $wpdb->users u WHERE u.ID = '$value'", OBJECT );
        if ( $author[0]->user_url ) {
            $value = '<a href="' . $author[0]->user_url . '">' . $author[0]->display_name . '</a>';
        } else {
            $value = $author[0]->display_name;
        }
    }
    return $value;
}

# filter for alt media fields

function url_to_media( $value, $field, $type, $classes, $group_index, $field_index, $post_id ) {
    if ( !$post_id || !$group_index || !$field_index ) { return $value; }
    if ( $type === 'alt_embed' ) {
        $value = alt_embed_field::get_embed( $field, $group_index, $field_index, $post_id );
    } else if ( $type === 'alt_video' ) {
        $value = alt_video_field::get_video( $field, $group_index, $field_index, $post_id );
    } else if ( $type === 'alt_audio' ) {
        $value = alt_audio_field::get_audio( $field, $group_index, $field_index, $post_id );
    } else if ( $type === 'alt_image' ) {
        $value = alt_image_field::get_image( $field, $group_index, $field_index, $post_id );
    }
    return $value;
}

function media_url_to_link( $value, $field, $type ) {
    if ( $type === 'alt_embed' || $type === 'alt_video' || $type === 'alt_audio' || $type === 'alt_image' ) {
        return '<a href="' . $value . '">' . $value . '</a>';
    }
    return $value;
}
    
?>