<?php declare( strict_types = 1 );

namespace Forum;
use Forum\Models\Forms;

/**
 *  @package Forum\Page;
 *  @file Page.php
 *  @brief Special content pages that aren't part of the discussion
 */
class Page {
	
	/**
	 *  Convert a page into a JSON package
	 */
	public static function format( 
		string $title, 
		string $slug	= '', 
		string $pub	= '', 
		string $body	= '' 
	) : string {
		$data	= 
		array(
			'title'	=> array( 'page_title', $title, 'text', 'title' ),
			'slug'	=> array( 'page_slug', $slug, 'text', 'slug' ),
			'pub'	=> array( 'page_pub', $pub, 'text', 'datetime' ),
			'body'	=> array( 'page_body', $body, 'area', 'html' )
		);
		
		return Text::toJSON( $data );
	}
	
	/**
	 *  Merge changes into a page type formatted array
	 *  
	 *  @params array $vars Original content to change
	 *  @params array $values New content
	 */
	public static function merge( 
		array $vars, 
		array $values 
	) : array {
		$filter	= Forms\Filter::getInstance();
		$mod	= array_intersect_key( $vars, $values );
		
		foreach ( $mod as $k => $v ) {
			switch ( $vars[$k][3] ) {
				case 'title':					
					$vars[$k][1] = 
					$filter->entities( 
						$filter->smartTrim( $values[$k] ) 
					);
					break;
					
				case 'datetime':
					$vars[$k][1] = strtotime( $values[$k]);
					break;
					
				case 'slug':
					$vars[$k][1] = $filter->slugify( $values[$k] );
					break;
					
				case 'html':
					$vars[$k][1] = $filter->clean( $values[$k] );
					break;
					
				default:
					$vars[$k][1] = $filter->entities( $values[$k] );
			}
		}
		
		return $vars;
	}
}

