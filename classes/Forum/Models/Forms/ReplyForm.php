<?php declare( strict_types = 1 );
/**
 *  @package Forum\Models\Forms\ReplyForm
 *  @file ReplyForm.php
 *  @brief Topic reply form
 */
namespace Forum\Models\Forms;

class ReplyForm extends PostForm {
	
	public static function generate(  
		string	$mode, 
		array	$params
	) : string {
		$params['{csrf}'] = static::getCsrf( 'replyform' );
		switch( $mode ) {
			case 'new' :
				return strtr( \TPL_REPLY_FORM, $params );
				
			case 'edit' :
				return strtr( \TPL_EDIT_FORM, $params );
				
			case 'modedit' :
				return strtr( \TPL_MOD_EDIT_FORM, $params );
		}
		
		return '';
	}
}

