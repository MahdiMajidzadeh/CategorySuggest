<?php
/* CategorySuggest Mediawiki Extension
 *
 * @author Andreas Rindler (mediawiki at jenandi dot com)
 * @credits Jared Milbank, Leon Weber <leon.weber@leonweber.de>, Manuel Schneider <manuel.schneider@wikimedia.ch>, Daniel Friesen http://wiki-tools.com, Adam Franco <afranco@middlebury.edu>
 * @licence GNU General Public Licence 2.0 or later
 * @description Adds input box to edit and upload page which allows users to assign categories to the article. When a user starts typing the name of a category, the extension queries the database to find categories that match the user input."
 *
*/

if( !defined( 'MEDIAWIKI' ) ) {
	echo( "This file is an extension to the MediaWiki software and cannot be used standalone.\n" );
	die();
}

/*************************************************************************************/
## Entry point for the hook and main worker function for editing the page:
function fnCategorySuggestShowHook( $m_isUpload = false, &$m_pageObj ) {
global $wgOut, $wgParser, $wgTitle, $wgRequest;
	global $wgTitle, $wgScriptPath, $wgCategorySuggestCloud, $wgCategorySuggestjs;

	# Get ALL categories from wiki:
//		$m_allCats = fnAjaxSuggestGetAllCategories();
	# Get the right member variables, depending on if we're on an upload form or not:
	if( !$m_isUpload ) {
		# Check if page is subpage once to save method calls later:
		$m_isSubpage = $wgTitle->isSubpage();

		# Check if page has been submitted already to Preview or Show Changes
		$strCatsFromPreview = trim($wgRequest->getVal('txtSelectedCategories'), '; ');
		if(strlen($strCatsFromPreview)==0){
			# Extract all categorylinks from PAGE:
			$m_pageCats = fnCategorySuggestGetPageCategories( $m_pageObj );
		} else {
		 	# Get cats from preview
			$m_pageCats = explode(";",$strCatsFromPreview);
		}
		# Never ever use editFormTextTop here as it resides outside the <form> so we will never get contents
		$m_place = 'editFormTextAfterWarn';
		# Print the localised title for the select box:
		$m_textBefore = '<b>'. wfMessage( 'categorysuggest-title' )->text() . '</b>:';
	} else	{
		# No need to get categories:
		$m_pageCats = array();

		# Place output at the right place:
		$m_place = 'uploadFormTextAfterSummary';
	}

	#ADD EXISTING CATEGORIES TO INPUT BOX
	$arrExistingCats = array();
	$arrExistingCats = $m_pageCats;

	#ADD JAVASCRIPT - use document.write so it is not presented if javascript is disabled.
	$m_pageObj->$m_place .= "<script type=\"text/javascript\">/*<![CDATA[*/ var categorysuggestSelect = \"". wfMessage( 'categorysuggest-select' )->text() ."\"; /*]]>*/</script>\n";
	$m_pageObj->$m_place .= "<script type=\"text/javascript\" src=\"" . $wgCategorySuggestjs . "\"></script>\n";
	$m_pageObj->$m_place .= "<script type=\"text/javascript\">/*<![CDATA[*/\n";
	$m_pageObj->$m_place .= "document.write(\"<div id='categoryselectmaster'><div><b>" .wfMsg( 'categorysuggest-title' ). "</b></div>\");\n";
	$m_pageObj->$m_place .= "document.write(\"<p>" . wfMsg( 'categorysuggest-subtitle' ). "</p>\");\n";
	$m_pageObj->$m_place .= "document.write(\"<table width='100%' style='position:relative'><tr><td><label for='txtSelectedCategories'>" .wfMsg( 'categorysuggest-boxlabel' ).":</label></td>\");\n";
	$catList = str_replace("_"," ",implode(";", $arrExistingCats));
	$catList = str_replace("'", "&#039;", $catList);
	if (!empty($catList))
		$catList .= ';';
	$m_pageObj->$m_place .= "document.write(\"<td width=100%><div style='position:relative' width='100%'><input onkeyup='sendRequest(this,event);' onkeydown='return checkSelect(this, event)' autocomplete='off' type='text' name='txtSelectedCategories' id='txtSelectedCategories' length='150' value='".$catList."'/>\");\n";
	$m_pageObj->$m_place .= "document.write(\"<br/><div id='searchResults'></div></div></td>\");\n";
	$m_pageObj->$m_place .= "document.write(\"<td></td></tr></table>\");\n";
	$m_pageObj->$m_place .= "document.write(\"<input type='hidden' value='" . $wgCategorySuggestCloud . "' id='txtCSDisplayType'/>\");\n";
//	$m_pageObj->$m_place .= "document.write(\"<input type='hidden' id='txtCSCats' name='txtCSCats' value='".str_replace("_"," ",implode(";", $arrExistingCats))."'/>\");\n";
	$m_pageObj->$m_place .= "document.write(\"</div>\");\n";
	$m_pageObj->$m_place .= "/*]]>*/</script>\n";

	return true;
}

/*************************************************************************************/
## Entry point for the hook and main worker function for saving the page:
function fnCategorySuggestSaveHook( $m_isUpload, $m_pageObj ) {
	global $wgContLang;
	global $wgOut;

	# Get localised namespace string:
	$m_catString = $wgContLang->getNsText( NS_CATEGORY );
	# Get some distance from the rest of the content:
	$m_text = "\n";

	# Assign all selected category entries:
	$strSelectedCats = $_POST['txtSelectedCategories'];

	#CHECK IF USER HAS SELECTED ANY CATEGORIES
	if(strlen($strSelectedCats)>1){
		$arrSelectedCats = array();
		$arrSelectedCats = explode(";",$_POST['txtSelectedCategories']);

	 	foreach( $arrSelectedCats as $m_cat ) {
	 	 	if(strlen($m_cat)>0){
				$m_cat = Title::capitalize($m_cat, NS_CATEGORY);
				$m_text .= "\n[[$m_catString:" . trim($m_cat) . "]]";
			}
		}
		# If it is an upload we have to call a different method:
		if ( $m_isUpload ) {
			$m_pageObj->mUploadDescription .= $m_text;
		} else{
			$m_pageObj->textbox1 .= $m_text;
		}
	}
	$wgOut->addHTML($m_text);

	# Return to the let MediaWiki do the rest of the work:
	return true;
}

/*************************************************************************************/
## Entry point for the CSS:
function fnCategorySuggestOutputHook( &$m_pageObj, $m_parserOutput ) {
	global $wgScriptPath, $wgCategorySuggestcss;

	# Register CSS file for input box:
	$m_pageObj->addLink(
		array(
			'rel'	=> 'stylesheet',
			'type'	=> 'text/css',
			'href'	=> $wgCategorySuggestcss
		)
	);

	return true;
}

/*************************************************************************************/
## Returns an array with the categories the articles is in.
## Also removes them from the text the user views in the editbox.
function fnCategorySuggestGetPageCategories( $m_pageObj ) {
global $wgOut;

	# Get page contents:
	$m_pageText = $m_pageObj->textbox1;

	# Split the page on <nowiki> and <noinclude> tags so that we can avoid pulling categories out of them.
	$blocks = preg_split('#(<nowiki>|</nowiki>|<noinclude>|</noinclude>|<includeonly>|</includeonly>|{{|}})#u', $m_pageText , null, PREG_SPLIT_DELIM_CAPTURE);
	$nowikiStack = array(); // Stack for nowiki nested state.
	$noincludeStack = array(); // Stack for noinclude nested state.
	$includeonlyStack = array(); // Stack for includeonly nested state.
	$templateStack = array(); // Stack for template nested state.
	$foundCategories = array();
	$outputText = '';
	foreach ($blocks as $block) {
		// If we encounter a closing </nowiki> or </noinclude> tag, remove them from our stack and continue.
		if (!empty($nowikiStack) && $block == '</nowiki>') {
			$outputText .= $block;
			array_pop($nowikiStack);
			continue;
		}
		if (!empty($noincludeStack) && $block == '</noinclude>') {
			$outputText .= $block;
			array_pop($noincludeStack);
			continue;
		}
		if (!empty($includeonlyStack) && $block == '</includeonly>') {
			$outputText .= $block;
			array_pop($includeonlyStack);
			continue;
		}
		if (!empty($templateStack) && $block == '}}') {
			$outputText .= $block;
			array_pop($templateStack);
			continue;
		}
		// If we encounter a <nowiki> or <noinclude> tag, add it to our stacks.
		if ($block == '<nowiki>') {
			array_push($nowikiStack, true);
		}
		if ($block == '<noinclude>') {
			array_push($noincludeStack, true);
		}
		if ($block == '<includeonly>') {
			array_push($includeonlyStack, true);
		}
		if ($block == '{{') {
			array_push($templateStack, true);
		}
		// Just pass through any content nested inside <nowiki> or <noinclude> tags.
		if (!empty($nowikiStack) || !empty($noincludeStack) || !empty($includeonlyStack) || !empty($templateStack)) {
			$outputText .= $block;
		}
		// If we are outside noinclude or nowiki tags, pull out categories
		else {
			$outputText .= fnCategorySuggestStripCats($block, $foundCategories);
		}
	}

	//Place cleaned text back into the text box:
	$m_pageObj->textbox1 = trim( $outputText );

	return $foundCategories;

}

function fnCategorySuggestStripCats($texttostrip, &$catsintext){
	global $wgContLang, $wgOut;

	# Get localised namespace string:
	$m_catString = strtolower( $wgContLang->getNsText( NS_CATEGORY ) );
	# The regular expression to find the category links:
	$m_pattern = "\[\[({$m_catString}|category):([^\]]*)\]\]";
	$m_replace = "$2";
	# The container to store all found category links:
	$m_catLinks = array ();
	# The container to store the processed text:
	$m_cleanText = '';


	# Check linewise for category links:
	foreach( explode( "\n", $texttostrip ) as $m_textLine ) {
		# Filter line through pattern and store the result:
        $m_cleanText .= rtrim( preg_replace( "/{$m_pattern}/i", "", $m_textLine ) ) . "\n";

		# Check if we have found a category, else proceed with next line:
        if( preg_match_all( "/{$m_pattern}/i", $m_textLine,$catsintext2,PREG_SET_ORDER) ){
			 foreach( $catsintext2 as $local_cat => $m_prefix ) {
				//Set first letter to upper case to match MediaWiki standard
				$strFirstLetter = substr($m_prefix[2], 0,1);
				strtoupper($strFirstLetter);
				$newString = strtoupper($strFirstLetter) . substr($m_prefix[2], 1);
				array_push($catsintext,$newString);

	 		}
			# Get the category link from the original text and store it in our list:
			preg_replace( "/.*{$m_pattern}/i", $m_replace, $m_textLine,-1,$intNumber );
		}

	}

	return $m_cleanText;

}
?>
