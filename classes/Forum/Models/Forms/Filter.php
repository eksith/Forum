<?php declare( strict_types = 1 );

namespace Forum\Models\Forms;
use Forum;

/**
 *  @package Forum\Models\Forms\Filter
 *  @file Filter.php
 *  @brief Content filtering for body/messages. HTML and Markdown
 */
class Filter extends Forum\Singleton {
	
	const RX_URL	= '~^(http|ftp)(s)?\:\/\/((([a-z|0-9|\-]{1,25})(\.)?){2,9})($|/.*$){4,255}$~i';
	const RX_XSS2	= '/(<(s(?:cript|tyle)).*?)/ism';
	const RX_XSS3	= '/(document\.|window\.|eval\(|\(\))/ism';
	const RX_XSS4	= '/(\\~\/|\.\.|\\\\|\-\-)/sm';
	
	/**
	 *  @var array Whitelist of allowed tags and associated attributes
	 */
	private static $white	= 
	array(
		// Block elements
		'p'		=> array( 'style', 'class', 'align', 
					'data-pullquote', 'data-video', 
					'data-media' ),
		// Data-* is for special formatting to be handled via 
		// CSS and your templates
		
		'div'		=> array( 'style', 'class', 'align' ),
		'span'		=> array( 'style', 'class' ),
		'br'		=> array( 'style', 'class' ),
		'hr'		=> array( 'style', 'class' ),
		
		// Headings
		'h1'		=> array( 'style', 'class' ),
		'h2'		=> array( 'style', 'class' ),
		'h3'		=> array( 'style', 'class' ),
		'h4'		=> array( 'style', 'class' ),
		'h5'		=> array( 'style', 'class' ),
		'h6'		=> array( 'style', 'class' ),
		
		// Styling
		'strong'	=> array( 'style', 'class' ),
		'em'		=> array( 'style', 'class' ),
		'u'	 	=> array( 'style', 'class' ),
		'strike'	=> array( 'style', 'class' ),
		'del'		=> array( 'style', 'class', 'cite' ),
		
		// Lists
		'ol'		=> array( 'style', 'class' ),
		'ul'		=> array( 'style', 'class' ),
		'li'		=> array( 'style', 'class' ),
		
		// Preformatted text
		'code'		=> array( 'style', 'class' ),
		'pre'		=> array( 'style', 'class' ),
		
		// Sub/superscript
		'sup'		=> array( 'style', 'class' ),
		'sub'		=> array( 'style', 'class' ),
		
		// Links and images
		'a'		=> array( 'style', 'class', 'rel', 
				'title', 'href' ),
		'img'		=> array( 'style', 'class', 'src', 
				'height', 'width', 'alt', 'longdesc', 
				'title', 'hspace', 'vspace' ),
		
		// Tables
		'table'		=> array( 'style', 'class', 
				'border-collapse', 'cellspacing', 
				'cellpadding' ),
		'thead'		=> array( 'style', 'class' ),
		'tbody'		=> array( 'style', 'class' ),
		'tfoot'		=> array( 'style', 'class' ),
		'tr'		=> array( 'style', 'class' ),
		'td'		=> array( 'style', 'class', 
				'colspan', 'rowspan' ),
		'th'		=> array( 'style', 'class', 'scope', 
				'colspan', 'rowspan' ),
		
		// Quotations
		'q'		=> array( 'style', 'class', 'cite' ),
		'cite'		=> array( 'style', 'class' ),
		'abbr'		=> array( 'style', 'class' ),
		'blockquote'	=> array( 'style', 'class', 'cite' ),
		
		// Stripped out
		'body'		=> array()
	);
	
	/**
	 *  @var array Administrator approved extra tags
	 */
	private static $admin_white	= 
	array(
		'div'		=> array( 'style', 'class' ),
		'section'	=> array( 'style', 'class' ),
		'aside'		=> array( 'style', 'class' ),
		'label'		=> array( 'style', 'class', 'for' ),
		'span'		=> array( 'style', 'class' ),
		'input'		=> array( 'style', 'class', 'name', 
					'type', 'value', 'range', 
					'pattern' ),
		'select'	=> array( 'name', 'style', 
					'class' ),
		'option'	=> array( 'value' ),
		'textarea'=> array( 'style', 'class', 'name' )
	);
	
	/**
	 *  @var array Media embedded tags
	 */
	private static $embed_tags	=
	array(
		'/\[youtube http(s)?\:\/\/(www)?\.?youtube\.com\/watch\?v=([0-9a-z_]*)\]/is'
		=> 
		'<div class="media"><iframe width="560" height="315" ' . 
			'src="https://www.youtube.com/embed/$3" ' . 
			'frameborder="0" allowfullscreen></iframe></div>',
		
		'/\[youtube http(s)?\:\/\/(www)?\.?youtu\.be\/([0-9a-z_]*)\]/is'
		=> '<div class="media"><iframe width="560" height="315" ' . 
			'src="https://www.youtube.com/embed/$3" frameborder="0" ' . 
			'allowfullscreen></iframe></div>',
		
		'/\[youtube ([0-9a-z_]*)\]/is'
		=>  '<div class="media"><iframe width="560" height="315" '.
			'src="https://www.youtube.com/embed/$1" ' . 
			' frameborder="0" allowfullscreen></iframe></div>',
		
		'/\[vimeo ([0-9]*)\]/is'
		=> '<div class="media"><iframe ' . 
			'src="https://player.vimeo.com/video/$1?portrait=0" ' . 
			'width="500" height="281" frameborder="0" ' . 
			'webkitallowfullscreen mozallowfullscreen ' . 
			'allowfullscreen></iframe></div>',
		
		'/\[vimeo http(s)?\:\/\/(www)?\.?vimeo\.com\/([0-9]*)\]/is'
		=> '<div class="media"><iframe ' . 
			'src="https://player.vimeo.com/video/$3?portrait=0" ' . 
			'width="500" height="281" frameborder="0" ' . 
			'webkitallowfullscreen mozallowfullscreen ' . 
			'allowfullscreen></iframe></div>'
	);
	
	/**
	 *  Convert a string into a page slug
	 */
	public function slugify( string $text, string $title ) : string {
		if ( empty( $text ) ) {
			$text = $title;
		}
		
		$text = preg_replace( '~[^\\pL\d]+~u', ' ', $text );
		$text = preg_replace( '/\s+/', '-', trim( $text ) );
		
		if ( empty( $text ) ) {
			return hash( 'md5', $title );
		}
		
		return strtolower( $this->smartTrim( $text ) );
	}
	
	/**
	 *  Limit a string without cutting off words
	 */
	public function smartTrim( 
		string	$val, 
		int	$max	= 100 
	) : string {
		$val	= trim( $val );
		$len	= mb_strlen( $val );
		
		if ( $len <= $max ) {
			return $val;
		}
		
		$out	= '';
		$words	= preg_split( '/([\.\s]+)/', $val, -1, 
				\PREG_SPLIT_OFFSET_CAPTURE | 
				\PREG_SPLIT_DELIM_CAPTURE );
		
		for ( $i = 0; $i < count( $words ); $i++ ) {
			$w	= $words[$i];
			// Add if this word's length is less than length
			if ( $w[1] <= $max ) {
				$out .= $w[0];
			}
		}
		
		$out	= preg_replace( "/\r?\n/", '', $out );
		
		// If there's too much overlap
		if ( mb_strlen( $out ) > $max + 10 ) {
			$out = mb_substr( $out, 0, $max );
		}
		
		return $out;
	}
	
	/**
	 *  Extract the first line as a title from the body
	 */
	public function fillTitle( string $body ) : string {
		$body	= trim( $body );
		$body	= str_replace( "\r\n", "\r", $body );
		$title	= strtok( strip_tags( $body ), "\n" );
		
		if ( false !== $title ) {
			return $this->smartTrim( $title, MAX_TITLE );
		}
		return 'none';
	}
	
	/**
	 *  HTML safe character entities in UTF-8
	 *  
	 *  @return string
	 */
	public function entities( 
		string	$v, 
		bool	$quotes	= true 
	) : string {
		if ( $quotes ) {
			return \htmlentities( 
				\iconv( 'UTF-8', 'UTF-8', $v ), 
				\ENT_QUOTES | \ENT_SUBSTITUTE, 
				'UTF-8'
			);
		}
		
		return \htmlentities( 
			\iconv( 'UTF-8', 'UTF-8', $v ), 
			\ENT_NOQUOTES | \ENT_SUBSTITUTE, 
			'UTF-8'
		);
	}
	
	/**
	 *  Filter URL 
	 *  
	 *  @param string $txt Raw URL attribute value
	 */
	public function cleanUrl( 
		string	$txt, 
		bool	$xss	= true, 
		string	$prefix	= '' 
	) : string {
		if ( empty( $txt ) ) {
			return '';
		}
	
		if ( filter_var( $txt, \FILTER_VALIDATE_URL ) ) {
			if ( $xss ) {
				if ( !preg_match( RX_URL, $txt ) ){
					return '';
				}	
			}
			
			if ( 
				preg_match( self::RX_XSS2, $txt ) || 
				preg_match( self::RX_XSS3, $txt ) || 
				preg_match( self::RX_XSS4, $txt ) 
			) {
				return '';
			}
			
			return  $txt;
		}
		return $this->entities( $prefix . $txt );
	}
	
	/**
	 *  Clean DOM node attribute against whitelist
	 *  
	 *  @param $node object DOM Node
	 */
	public function cleanAttributes(
		\DOMNode	&$node,
		array		$white
	) {
		if ( !$node->hasAttributes() ) {
			return null;
		}
		
		foreach ( \iterator_to_array( 
			$node->attributes 
		) as $at ) {
			$n = $at->nodeName;
			$v = $at->nodeValue;
			
			// Default action is to remove attribute
			// It will only get added if it's safe
			$node->removeAttributeNode( $at );
			if ( in_array( $n, $white[$node->nodeName] ) ) {
				switch( $n ) {
					case 'longdesc':
					case 'url':
					case 'src':
					case 'href':
						$v = $this->cleanUrl( $v );
						break;
						
					default:
						$v = $this->entities( $v );
				}
				
				$node->setAttribute( $n, $v );
			}
		}
	}
	
	/**
	 *  Scrub each node against white list
	 */
	public function scrub(
		\DOMNode	$node,
		array		$white,
		array		&$flush		= array()
	) {
		if ( isset( $white[$node->nodeName] ) ) {
			// Clean attributes first
			$this->cleanAttributes( $node, $white );
			if ( $node->childNodes ) {
				// Continue to other tags
				foreach ( $node->childNodes as $child ) {
					$this->scrub( 
						$child, $white, $flush 
					);
				}
			}
		
		} elseif ( $node->nodeType == \XML_ELEMENT_NODE ) {
			// This tag isn't on the whitelist
			$flush[] = $node;
		}
	}
	
	/**
	 *  Clean user provided HTML
	 *  
	 *  @param string $html Raw HTML
	 *  @param bool $mark Apply Markdown formatting
	 *  @param bool $admin Allow Administrator whitelist tags
	 *  
	 *  @return string Cleaned and formatted HTML
	 */
	public function clean(
		string	$html, 
		bool	$mark		= true,
		bool	$admin		= false 
	) : string {
		
		$white = static::$white;
		
		// Administrators get extra element types
		if ( $admin ) {
			array_push( $white, static::$admin_white );
		}
		
		// Preliminary cleaning
		$html		= Forum\Text::pacify( $html, true );
		
		// Apply Markdown formatting
		$html		= $this->markdown( $html );
		
		// Clean up HTML
		$html		= $this->tidyup( $html );
		
		$ent		= \libxml_disable_entity_loader( true );
		$err		= \libxml_use_internal_errors( true );
		
		$dom		= new \DOMDocument();
		$dom->loadHTML( 
			$html, 
			\LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD | 
			\LIBXML_NOERROR | \LIBXML_NOWARNING | 
			\LIBXML_NOXMLDECL | \LIBXML_COMPACT | 
			\LIBXML_NOCDATA | \LIBXML_NONET
		);
		
		$domBody	= 
		$dom->getElementsByTagName( 'body' )->item( 0 );
		
		// Failed nodes
		$flush		= array();
		
		// Iterate through every HTML element 
		foreach( $domBody->childNodes as $node ) {
			$this->scrub( $node, $white, $flush );
		}
		
		// Remove any tags not found in the whitelist
		if ( !empty( $flush ) ) {
			foreach( $flush as $node ) {
				if ( $node->nodeName == '#text' ) {
					continue;
				}
				
				// Replace tag with harmless text
				$safe	= $dom->createTextNode( 
						$dom->saveHTML( $node )
					);
				$node->parentNode
					->replaceChild( $safe, $node );
			}
		}
		
		$clean		= '';
		foreach ( $domBody->childNodes as $node ) {
			$clean .= $dom->saveHTML( $node );
		}
		
		\libxml_disable_entity_loader( $ent );
		\libxml_clear_errors();
		\libxml_use_internal_errors( $err );
		
		$clean		= $this->embeds( $clean );
		return trim( $clean );
	}
	
	/**
	 *  Tidy settings
	 */
	private function tidyup( string $text ) : string {
		if ( missing( 'tidy_repair_string' ) ) {
			return $text;
		}
		
		$opt = array(
			'bare'				=> 1,
			'hide-comments' 		=> 1,
			'drop-proprietary-attributes'   => 1,
			'fix-uri'			=> 1,
			'join-styles'			=> 1,
			'output-xhtml'			=> 1,
			'merge-spans'			=> 1,
			'show-body-only'		=> 0,
			'wrap'				=> 0
		);
		
		return trim( \tidy_repair_string( $text, $opt ) );
	}
	
	/**
	 *  Embedded Big Brother silo media
	 */
	public function embeds( $html ) {
		return 
		preg_replace( 
			array_keys( static::$embed_tags ), 
			array_values( static::$embed_tags ), 
			$html 
		);
	}
	
	/**
	 *  Convert Markdown formatted text into HTML tags
	 *  This is only a small subset of formatting
	 *  
	 *  Inspired by : 
	 *  @link https://gist.github.com/jbroadway/2836900
	 */
	public function markdown( 
		string	$html, 
		string	$prefix = '' 
	) : string {
		$filters	= 
		array(
			// Links / Images with alt text
			'/(\!)?\[([^\[]+)\]\(([^\)]+)\)/s'	=> 
			function( $m ) use ( $prefix ) {
				$i = trim( $m[1] );
				$t = trim( $m[2] );
				$u = cleanUrl( trim( $m[3] ), true, $prefix );
				
				return empty( $i ) ?
				sprintf( "<a href='%s'>%s</a>", $t, $u ) :
				sprintf( "<img src='%s' alt='%s' />", $u, $t );
			},
			
			// Bold / Italic / Deleted / In-line quote
			'/(\*(\*)?|_(_)?|\~\~|\:\")(.*?)\1/'	=>
			function( $m ) {
				$i = strlen( $m[1] );
				$t = trim( $m[4] );
				
				switch( true ) {
					// Strike through / Deleted text
					case ( false !== strpos( $m[1], '~' ) ):
						return sprintf( "<del>%s</del>", $t );
					
					// Short, in-line quotation
					case ( false !== strpos( $m[1], ':' ) ):
						return sprintf( "<q>%s</q>", $t );
					
					// Double (bold) vs. single (italic) asterisks
					default:
						return ( $i > 1 ) ?
						sprintf( "<strong>%s</strong>", $t ) : 
						sprintf( "<em>%s</em>", $t );
				}
			},
			
			// Headings
			'/([#]{1,6}+)\s?(.+)/'			=>
			function( $m ) {
				$h = strlen( trim( $m[1] ) );
				$t = trim( $m[2] );
				return sprintf( "<h%s>%s</h%s>", $h, $t, $h );
			}, 
			
			// List items
			'/\n(\*|([0-9]\.+))\s?(.+)/'		=>
			function( $m ) {
				$i = strlen( $m[2] );
				$t = trim( $m[3] );
				
				return ( $i > 1 ) ?
				sprintf( '<ol><li>%s</li></ol>', $t ) : 
				sprintf( '<ul><li>%s</li></ul>', $t );
			},
			
			// Merge duplicate lists
			'/<\/(ul|ol)>\s?<\1>/'			=> 
			function( $m ) { return ''; },
			
			// Blockquotes
			'/\n\>\s(.*)/'				=> 
			function( $m ) {
				$t = trim( $m[1] );
				return 
				sprintf( '<blockquote><p>%s</p></blockquote>', $t );
			},
			
			// Merge duplicate blockquotes
			'/<\/(p)><\/(blockquote)>\s?<\2>/'	=>
			function( $m ) { return ''; },
			
			// Block of code
			'/\n`{3,}(.*)\n`{3,}/'			=>
			function( $m ) { 
				$t = trim( $m[1] );
				return 
				sprintf( '\n<pre><code>%s</code></pre>\n', $t );
			},
			
			// Inline code
			'/`(.*)`/'				=>
			function( $m ) {
				$t = trim( $m[1] );
				return sprintf( '<code>%s</code>', $t );
			},
			
			// Horizontal rule
			'/\n-{5,}/'				=>
			function( $m ) { return '<hr />'; },
			
			// Fix paragraphs after block elements
			'/\n([^\n(\<\/ul|ol|li|h|blockquote|code|pre)?]+)\n/' =>
			function( $m ) {
				return '</p><p>';
			}
		);
		
		return 
		trim( preg_replace_callback_array( $filters, $html ) );
	}
}

