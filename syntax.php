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
        $this->Lexer->addSpecialPattern('{{hash>.+?}}', $mode, 'plugin_odtsupport');
        $this->Lexer->addSpecialPattern('{{pagehash}}', $mode, 'plugin_odtsupport');
        $this->Lexer->addSpecialPattern('{{pagehash4}}', $mode, 'plugin_odtsupport');
        $this->Lexer->addSpecialPattern('{{'.$this->odt_field_pagehash.'>.+?}}', $mode, 'plugin_odtsupport');
        $this->Lexer->addSpecialPattern('{{'.$this->odt_field_pagehash4.'>.+?}}', $mode, 'plugin_odtsupport');
        $this->Lexer->addSpecialPattern('{{'.$this->odt_field_metadata.'>.+?}}', $mode, 'plugin_odtsupport');
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
				
		switch ($command) {
			case "hash":
				$string = substr($match, 7, -2); //strip markup
				$hash = hash(hash_hmac_algos()[0], $string);
				break;
			case "pagehash4":
				$hash = substr($hash,0,4);
				break;
			case "pagehash":
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
		
		return array($command,$string,$hash);
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
        list($command, $string, $hash) = $data;
		
        if ($mode == 'xhtml') {
            if ($command != $this->odt_field_pagehash4
			&& $command != $this->odt_field_pagehash
			&& $command != $this->odt_field_metadata) {
				$renderer->doc .= $hash;
			}
			return true;
        } elseif ($mode == 'text') {
			// make the hash code searchable (usage of plugins searchtext and text necessary!)
			$renderer->doc .= $hash;
            return true;
        } elseif ($mode == 'odt') {
            if ($command == $this->odt_field_pagehash4
			|| $command == $this->odt_field_pagehash
			|| $command == $this->odt_field_metadata) {
				$this->_fieldsODTAddUserField($renderer, $string, $renderer->_xmlEntities($hash));
			}
			return true;
        }

        return true;
    }

    function _fieldsODTFilterUserFieldName($name) {
        // keep only allowed chars in the name
        return preg_replace('/[^a-zA-Z0-9_.]/', '', $name);
    }

    function _fieldsODTAddUserField(&$renderer, $name, $value) {
        if (!method_exists ($renderer, 'addUserField')) {
            $name = $this->_fieldsODTFilterUserFieldName($name);
            $renderer->fields[$name] = $value;
        } else {
            $renderer->addUserField($name, $value);
        }
    }

    function _fieldsODTInsertUserField(&$renderer, $name) {
        if (!method_exists ($renderer, 'insertUserField')) {
            $name = $this->_fieldsODTFilterUserFieldName($name);
            if (array_key_exists($name, $renderer->fields)) {
                return '<text:user-field-get text:name="'.$name.'">'.$renderer->fields[$name].'</text:user-field-get>';
            }
        } else {
            $renderer->insertUserField($name);
        }
        return '';
    }
}

