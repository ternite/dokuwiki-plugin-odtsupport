<?php
/**
 * DokuWiki Plugin odtsupport (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Thomas Schäfer <thomas.schaefer@itschert.net>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}

class syntax_plugin_odtsupport extends DokuWiki_Syntax_Plugin
{
	private $odt_field_pagehash4 = 'pagehash4tofield';
	private $odt_field_pagehash = 'pagehashtofield';
	private $odt_field_metadata = 'metadatatofield';
	private $serverurl = 'serverurl';

	
    /**
     * @return string Syntax mode type
     */
    public function getType()
    {
        return 'substition';
    }

    /**
     * @return string Paragraph type
     */
    public function getPType()
    {
		return 'normal';
    }

    /**
     * @return int Sort order - Low numbers go before high numbers
     */
    public function getSort()
    {
        return 55;
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('{{hash>[-.:\d\w]+?}}', $mode, 'plugin_odtsupport');
        $this->Lexer->addSpecialPattern('{{hash4>[-.:\d\w]+?}}', $mode, 'plugin_odtsupport');
        $this->Lexer->addSpecialPattern('{{pagehash}}', $mode, 'plugin_odtsupport');
        $this->Lexer->addSpecialPattern('{{pagehash4}}', $mode, 'plugin_odtsupport');
        $this->Lexer->addSpecialPattern('{{'.$this->odt_field_pagehash.'>.+?}}', $mode, 'plugin_odtsupport');
        $this->Lexer->addSpecialPattern('{{'.$this->odt_field_pagehash4.'>.+?}}', $mode, 'plugin_odtsupport');
        $this->Lexer->addSpecialPattern('{{'.$this->odt_field_metadata.'>.+?}}', $mode, 'plugin_odtsupport');
        $this->Lexer->addSpecialPattern('{{'.$this->serverurl.'>.*?:.+?}}', $mode, 'plugin_odtsupport');
    }

    /**
     * Handle matches of the odtsupport syntax
     *
     * @param string       $match   The match of the syntax
     * @param int          $state   The state of the handler
     * @param int          $pos     The position in the document
     * @param Doku_Handler $handler The handler
     *
     * @return array Data for the renderer
     */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {	
		if (preg_match('/{{([a-zA-Z0-9]+)/', $match, $matches) !== 1) {
			return array();
		}
		// Der erste Match sollte genau ein Wort sein, das nach '{{' im String $match steht.
		$command = $matches[1];
		
		$pageid = pageinfo()['id'];
		$hash = hash(hash_hmac_algos()[0], $pageid);
		$string = "";
		$string2 = "";
				
		switch ($command) {
			case "hash":
				$string = substr($match, 7, -2); //strip markup
				$hash = hash(hash_hmac_algos()[0], $string);
				break;
			case "hash4":
				$string = substr($match, 8, -2); //strip markup
				$hash = hash(hash_hmac_algos()[0], $string);
				$hash = substr($hash,0,4);
				break;
			case "pagehash4":
				$hash = substr($hash,0,4);
				break;
			case "pagehash":
				break;
			case $this->serverurl:
				$params = substr($match, strlen($this->serverurl)+3, -2); //strip markup
				$params = str_replace("<PAGEHASH>",$hash,$params); // replace "<PAGEHASH>" keyword by hash
				$hash = substr($hash,0,4);
				$params = str_replace("<PAGEHASH4>",$hash,$params); // replace "<PAGEHASH4>" keyword by hash
				list($string,$string2) = explode(":",$params);
				break;
			case $this->odt_field_pagehash4:
				$string = substr($match, strlen($this->odt_field_pagehash4)+3, -2); //strip markup
				$hash = substr($hash,0,4);
				break;
			case $this->odt_field_pagehash:
				$string = substr($match, strlen($this->odt_field_pagehash)+3, -2); //strip markup
				break;
			case $this->odt_field_metadata:
				$string = substr($match, strlen($this->odt_field_metadata)+3, -2); //strip markup
				
				$metadata = p_get_metadata($pageid, $string, METADATA_RENDER_USING_CACHE);
				
				// check if the given string contains 'date'
				if (strpos($string,'date') !== false) {
					// convert the given date to a date string
					$hash = date($this->getConf('dateformat'), intval($metadata));
				} else {
					// take the raw data
					$hash = $metadata;
				}
				
				break;
		}
		
		return array($command,$string,$hash,$string2);
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string        $mode     Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer $renderer The renderer
     * @param array         $data     The data from the handler() function
     *
     * @return bool If rendering was successful.
     */
    public function render($mode, Doku_Renderer $renderer, $data)
    {
        list($command, $string, $hash, $string2) = $data;
		
        if ($mode == 'xhtml') {
            if ($command == $this->serverurl) {
				$url = $this->getConf('serverurl').$string;
				$renderer->doc .= "<a class='windows' href='".$url."'>".$string2."</a>";
			} else if ($command != $this->odt_field_pagehash4
					&& $command != $this->odt_field_pagehash
					&& $command != $this->odt_field_metadata) {
				$renderer->doc .= $hash;
			}
			return true;
        } elseif ($mode == 'text') {
			// make the hash code searchable (usage of plugins searchtext and text necessary!)
			if ($command != $this->odt_field_metadata) { // don't insert metadata - if this should be searchable, it may be printed onto the page
				$renderer->doc .= $hash;
				return true;
			}
        } elseif ($mode == 'odt') {
			if ($command == $this->serverurl) {
				$url = $this->getConf('serverurl').$string;
				
				// TODO: implement odt output
				
			} else if ($command == $this->odt_field_pagehash4
			|| $command == $this->odt_field_pagehash
			|| $command == $this->odt_field_metadata) {
				$fieldsPlugin = $this->loadHelper('fields');
				if ($fieldsPlugin) {
					$fieldsPlugin->ODTSetUserField($renderer, $string, $renderer->_xmlEntities($hash));
				}
			}
			return true;
        }

        return true;
    }
}

