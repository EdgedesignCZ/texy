<?php

/**
 * Texy! - plain text to html converter
 * ------------------------------------
 *
 * Copyright (c) 2004-2007 David Grudl aka -dgx- <dave@dgx.cz>
 *
 * for PHP 4.3.3 and newer
 *
 * @link      http://texy.info/
 * @license   GNU GENERAL PUBLIC LICENSE version 2
 * @package   Texy
 * @category  Text
 * @version   2.0 RC 1 (Revision: $WCREV$, Date: $WCDATE$)
 */


/**
 * This file is part of the Texy! formatter (http://texy.info/)
 *
 * Copyright (c) 2004-2007 David Grudl aka -dgx- <dave@dgx.cz>
 *
 * @version  $Revision$ $Date$
 * @package  Texy
 */



/**
 * Absolute filesystem path to the Texy package
 */
define('TEXY_DIR',  dirname(__FILE__).'/');

require_once TEXY_DIR.'libs/RegExp.Patterns.php';
require_once TEXY_DIR.'libs/TexyHtml.php';
require_once TEXY_DIR.'libs/TexyHtmlCleaner.php';
require_once TEXY_DIR.'libs/TexyModifier.php';
require_once TEXY_DIR.'libs/TexyModule.php';
require_once TEXY_DIR.'libs/TexyParser.php';
require_once TEXY_DIR.'libs/TexyUtf.php';
require_once TEXY_DIR.'libs/TexyConfigurator.php';
require_once TEXY_DIR.'modules/TexyParagraphModule.php';
require_once TEXY_DIR.'modules/TexyBlockModule.php';
require_once TEXY_DIR.'modules/TexyHeadingModule.php';
require_once TEXY_DIR.'modules/TexyHorizLineModule.php';
require_once TEXY_DIR.'modules/TexyHtmlModule.php';
require_once TEXY_DIR.'modules/TexyFigureModule.php';
require_once TEXY_DIR.'modules/TexyImageModule.php';
require_once TEXY_DIR.'modules/TexyLinkModule.php';
require_once TEXY_DIR.'modules/TexyListModule.php';
require_once TEXY_DIR.'modules/TexyLongWordsModule.php';
require_once TEXY_DIR.'modules/TexyPhraseModule.php';
require_once TEXY_DIR.'modules/TexyQuoteModule.php';
require_once TEXY_DIR.'modules/TexyScriptModule.php';
require_once TEXY_DIR.'modules/TexyEmoticonModule.php';
require_once TEXY_DIR.'modules/TexyTableModule.php';
require_once TEXY_DIR.'modules/TexyTypographyModule.php';




/**
 * PHP 4 Clone emulation
 *
 * Example: $obj = clone ($dolly)
 */
if (PHP_VERSION < 5) eval('
    function clone($obj)
    {
        foreach($obj as $key => $value) {
            $obj->$key = & $value;               // reference to new variable
            $GLOBALS[\'$$HIDDEN$$\'][] = & $value; // and generate reference
            unset($value);
        }

        // call $obj->__clone()
        if (is_callable(array(&$obj, \'__clone\'))) $obj->__clone();

        return $obj;
    }
');



define('TEXY_ALL',  TRUE); /* class constant */
define('TEXY_NONE',  FALSE); /* class constant */
define('TEXY_VERSION',  '2.0 FOR PHP4 RC 1 (Revision: $WCREV$, Date: $WCDATE$)'); /* class constant */
define('TEXY_CONTENT_MARKUP',  "\x17"); /* class constant */
define('TEXY_CONTENT_REPLACED',  "\x16"); /* class constant */
define('TEXY_CONTENT_TEXTUAL',  "\x15"); /* class constant */
define('TEXY_CONTENT_BLOCK',  "\x14"); /* class constant */
define('TEXY_PROCEED',  NULL); /* class constant */

/** @var bool  use Strict of Transitional DTD? */
$GLOBALS['Texy::$strictDTD'] = FALSE; /* class static property */



/**
 * Texy! - Convert plain text to XHTML format using {@link process()}
 *
 * <code>
 *     $texy = new Texy();
 *     $html = $texy->process($text);
 * </code>
 */
class Texy
{
    /** @var string  input & output text encoding */
    var $encoding = 'utf-8';

    /** @var array  Texy! syntax configuration */
    var $allowed = array();

     /** @var TRUE|FALSE|array  Allowed HTML tags */
    var $allowedTags;

    /** @var TRUE|FALSE|array  Allowed classes */
    var $allowedClasses = TEXY_ALL; // all classes and id are allowed

    /** @var TRUE|FALSE|array  Allowed inline CSS style */
    var $allowedStyles = TEXY_ALL;  // all inline styles are allowed

    /** @var int  TAB width (for converting tabs to spaces) */
    var $tabWidth = 8;

    /** @var boolean  Do obfuscate e-mail addresses? */
    var $obfuscateEmail = TRUE;

    /** @var array  regexps to check URL schemes */
    var $urlSchemeFilters = NULL; // disable URL scheme filter

    /** @var array  Parsing summary */
    var $summary = array(
        'images' => array(),
        'links' => array(),
        'preload' => array(),
    );

    /** @var string  Generated stylesheet */
    var $styleSheet = '';

    /** @var bool  Paragraph merging mode */
    var $mergeLines = TRUE;

    /** @var object  User handler object */
    var $handler;

    /** @var bool  ignore stuff with only markup and spaecs? */
    var $ignoreEmptyStuff = TRUE;

    var
        /** @var TexyScriptModule */
        $scriptModule,
        /** @var TexyParagraphModule */
        $paragraphModule,
        /** @var TexyHtmlModule */
        $htmlModule,
        /** @var TexyImageModule */
        $imageModule,
        /** @var TexyLinkModule */
        $linkModule,
        /** @var TexyPhraseModule */
        $phraseModule,
        /** @var TexyEmoticonModule */
        $emoticonModule,
        /** @var TexyBlockModule */
        $blockModule,
        /** @var TexyHeadingModule */
        $headingModule,
        /** @var TexyHorizLineModule */
        $horizLineModule,
        /** @var TexyQuoteModule */
        $quoteModule,
        /** @var TexyListModule */
        $listModule,
        /** @var TexyTableModule */
        $tableModule,
        /** @var TexyFigureModule */
        $figureModule,
        /** @var TexyTypographyModule */
        $typographyModule,
        /** @var TexyLongWordsModule */
        $longWordsModule;

    var $cleaner;


    /**
     * Registered regexps and associated handlers for inline parsing
     * @var array of ('handler' => callback
     *                'pattern' => regular expression)
     */
    var $linePatterns = array(); /* private */

    /**
     * Registered regexps and associated handlers for block parsing
     * @var array of ('handler' => callback
     *                'pattern' => regular expression)
     */
    var $blockPatterns = array(); /* private */


    /** @var TexyDomElement  DOM structure for parsed text */
    var $DOM; /* private */

    /** @var TexyModule[]  List of all modules */
    var $modules; /* private */

    /** @var array  Texy protect markup table */
    var $marks = array(); /* private */

    /** @var array  for internal usage */
    var $_classes, $_styles;

    /** @var array of ITexyPreBlock for internal parser usage */
    var $_preBlockModules;

    /** @var int internal state (0=new, 1=parsing, 2=parsed) */
    var $_state = 0; /* private */



    function __construct()
    {
        // load all modules
        $this->loadModules();

        // load routines
        $this->cleaner = new TexyHtmlCleaner($this);

        // accepts all valid HTML tags and attributes by default
        foreach ($GLOBALS['TexyHtmlCleaner::$dtd']as $tag => $dtd)
            $this->allowedTags[$tag] = is_array($dtd[0]) ? array_keys($dtd[0]) : $dtd[0];

        // examples of link references ;-)
        $link = new TexyLink('http://texy.info/');
        $link->modifier->title = 'The best text -> HTML converter and formatter';
        $link->label = 'Texy!';
        $this->linkModule->addReference('texy', $link);

        $link = new TexyLink('http://www.google.com/search?q=%s');
        $this->linkModule->addReference('google', $link);

        $link = new TexyLink('http://en.wikipedia.org/wiki/Special:Search?search=%s');
        $this->linkModule->addReference('wikipedia', $link);

        // mbstring.func_overload fix
        if (function_exists('mb_get_info')) {
            $mb = mb_get_info();
            if ($mb['func_overload'] & 2 && $mb['internal_encoding'][0] === 'U') { // U??
                mb_internal_encoding('pass');
                trigger_error('Texy: mb_internal_encoding changed to pass', E_USER_WARNING);
            }
        }
    }



    /**
     * Create array of all used modules ($this->modules)
     * This array can be changed by overriding this method (by subclasses)
     */
    function loadModules() /* protected */
    {
        // Line parsing - order is not important
        $this->scriptModule = new TexyScriptModule($this);
        $this->htmlModule = new TexyHtmlModule($this);
        $this->imageModule = new TexyImageModule($this);
        $this->phraseModule = new TexyPhraseModule($this);
        $this->linkModule = new TexyLinkModule($this);
        $this->emoticonModule = new TexyEmoticonModule($this);

        // block parsing - order is not important
        $this->paragraphModule = new TexyParagraphModule($this);
        $this->blockModule = new TexyBlockModule($this);
        $this->headingModule = new TexyHeadingModule($this);
        $this->horizLineModule = new TexyHorizLineModule($this);
        $this->quoteModule = new TexyQuoteModule($this);
        $this->listModule = new TexyListModule($this);
        $this->tableModule = new TexyTableModule($this);
        $this->figureModule = new TexyFigureModule($this);

        // post process - order is not important
        $this->typographyModule = new TexyTypographyModule($this);
        $this->longWordsModule = new TexyLongWordsModule($this);
    }



    function registerModule(/*TexyModule*/ $module)
    {
        $this->modules[] = $module;
    }



    function registerLinePattern($handler, $pattern, $name)
    {
        if (empty($this->allowed[$name])) return;
        $this->linePatterns[$name] = array(
            'handler'     => $handler,
            'pattern'     => $pattern,
        );
    }



    function registerBlockPattern($handler, $pattern, $name)
    {
        // if (!preg_match('#(.)\^.*\$\\1[a-z]*#is', $pattern)) die('Texy: Not a block pattern. Module '.get_class($module).', pattern '.htmlSpecialChars($pattern));
        if (empty($this->allowed[$name])) return;
        $this->blockPatterns[$name] = array(
            'handler'     => $handler,
            'pattern'     => $pattern  . 'm',  // force multiline
        );
    }




    /**
     * Convert Texy! document in (X)HTML code
     * This is shortcut for parse() & toHtml()
     *
     * @param string   input text
     * @param bool     is block or single line?
     * @return string  output html code
     */
    function process($text, $singleLine=FALSE)
    {
        $this->parse($text, $singleLine);
        return $this->toHtml();
    }



    /**
     * Makes only typographic corrections
     * @param string   input text
     * @return string  output code (in UTF!)
     */
    function processTypo($text)
    {
        // convert to UTF-8 (and check source encoding)
        $text = TexyUtf::toUtf($text, $this->encoding);

        // standardize line endings and spaces
        $text = Texy::normalize($text);

        $this->typographyModule->begin();
        $text = $this->typographyModule->postLine($text);

        return $text;
    }


    /**
     * Converts Texy! document into internal DOM structure ($this->DOM)
     * Before converting it normalize text and call all pre-processing modules
     *
     * @param string
     * @param bool     is block or single line?
     * @return void
     */
    function parse($text, $singleLine=FALSE)
    {
        if ($this->_state === 1) {
            trigger_error('Parsing is in progress yet.', E_USER_ERROR);
            return FALSE;
        }

         // initialization
        if ($this->handler && !is_object($this->handler)) {
            trigger_error('$texy->handler must be object. See documentation.', E_USER_ERROR);
            return FALSE;
        }

        $this->marks = array();
        $this->_state = 1;

        // speed-up
        if (is_array($this->allowedClasses)) $this->_classes = array_flip($this->allowedClasses);
        else $this->_classes = $this->allowedClasses;

        if (is_array($this->allowedStyles)) $this->_styles = array_flip($this->allowedStyles);
        else $this->_styles = $this->allowedStyles;

        $tmp = array($this->linePatterns, $this->blockPatterns);

        // convert to UTF-8 (and check source encoding)
        $text = TexyUtf::toUtf($text, $this->encoding);

        // standardize line endings and spaces
        $text = Texy::normalize($text);

        // replace tabs with spaces
        while (strpos($text, "\t") !== FALSE)
            $text = preg_replace_callback('#^(.*)\t#mU', array($this, 'tabCb'), $text);


        // init modules
        $this->_preBlockModules = array();
        foreach ($this->modules as $module) {
            $module->begin();

            if (isset($module->interface['ITexyPreBlock'])) $this->_preBlockModules[] = $module;
        }

        // parse!
        $this->DOM = TexyHtml::el();
        if ($singleLine)
            $this->DOM->parseLine($this, $text);
        else
            $this->DOM->parseBlock($this, $text, TRUE);

        // user handler
        if (is_callable(array($this->handler, 'afterParse')))
            $this->handler->afterParse($this, $this->DOM, $singleLine);

        // clean-up
        list($this->linePatterns, $this->blockPatterns) = $tmp;
        $this->_state = 2;
    }





    /**
     * Converts internal DOM structure to final HTML code
     * @return string
     */
    function toHtml()
    {
        if ($this->_state !== 2) {
            trigger_error('Call $texy->parse() first.', E_USER_ERROR);
            return FALSE;
        }

        $html = $this->_toHtml( $this->DOM->export($this) );

        // this notice should remain!
        if (!defined('TEXY_NOTICE_SHOWED')) {
            $html .= "\n<!-- by Texy2! -->";
            define('TEXY_NOTICE_SHOWED', TRUE);
        }

        $html = TexyUtf::utf2html($html, $this->encoding);

        return $html;
    }



    /**
     * Converts internal DOM structure to pure Text
     * @return string
     */
    function toText()
    {
        if ($this->_state !== 2) {
            trigger_error('Call $texy->parse() first.', E_USER_ERROR);
            return FALSE;
        }

        $text = $this->_toText( $this->DOM->export($this) );

        $text = TexyUtf::utfTo($text, $this->encoding);

        return $text;
    }



    /**
     * Converts internal DOM structure to final HTML code in UTF-8
     * @return string
     */
    function _toHtml($s)
    {
        // decode HTML entities to UTF-8
        $s = Texy::unescapeHtml($s);

        // line-postprocessing
        $blocks = explode(TEXY_CONTENT_BLOCK, $s);
        foreach ($this->modules as $module) {
            if (isset($module->interface['ITexyPostLine'])) {
                foreach ($blocks as $n => $s) {
                    if ($n % 2 === 0 && $s !== '')
                        $blocks[$n] = $module->postLine($s);
                }
            }
        }
        $s = implode(TEXY_CONTENT_BLOCK, $blocks);

        // encode < > &
        $s = Texy::escapeHtml($s);

        // replace protected marks
        $s = $this->unProtect($s);

        // wellform and reformat HTML
        $s = $this->cleaner->process($s);

        // unfreeze spaces
        $s = Texy::unfreezeSpaces($s);

        return $s;
    }



    /**
     * Converts internal DOM structure to final HTML code in UTF-8
     * @return string
     */
    function _toText($s)
    {
        $save = $this->cleaner->lineWrap;
        $this->cleaner->lineWrap = FALSE;
        $s = $this->_toHtml( $s );
        $this->cleaner->lineWrap = $save;

        // remove tags
        $s = preg_replace('#<(script|style)(.*)</\\1>#Uis', '', $s);
        $s = strip_tags($s);
        $s = preg_replace('#\n\s*\n\s*\n[\n\s]*\n#', "\n\n", $s);

        // entities -> chars
        $s = Texy::unescapeHtml($s);

        // convert nbsp to normal space and remove shy
        $s = strtr($s, array(
            "\xC2\xAD" => '',  // shy
            "\xC2\xA0" => ' ', // nbsp
        ));

        return $s;
    }




    /**
     * @deprecated
     */
    function safeMode()
    {
        trigger_error('$texy->safeMode() is deprecated. Use TexyConfigurator::safeMode($texy)', E_USER_WARNING);
        TexyConfigurator::safeMode($this);
    }



    /**
     * @deprecated
     */
    function trustMode()
    {
        trigger_error('$texy->trustMode() is deprecated. Trust configuration is by default.', E_USER_WARNING);
        TexyConfigurator::trustMode($this);
    }



    /**
     * Translate all white spaces (\t \n \r space) to meta-spaces \x01-\x04
     * which are ignored by TexyHtmlCleaner routine
     * @param string
     * @return string
     */
    function freezeSpaces($s) /* static */
    {
        return strtr($s, " \t\r\n", "\x01\x02\x03\x04");
    }



    /**
     * Reverts meta-spaces back to normal spaces
     * @param string
     * @return string
     */
    function unfreezeSpaces($s) /* static */
    {
        return strtr($s, "\x01\x02\x03\x04", " \t\r\n");
    }



    /**
     * Removes special controls characters and normalizes line endings and spaces
     * @param string
     * @return string
     */
    function normalize($s) /* static */
    {
        // remove special chars
        $s = preg_replace('#[\x01-\x04\x14-\x1F]+#', '', $s);

        // standardize line endings to unix-like
        $s = str_replace("\r\n", "\n", $s); // DOS
        $s = strtr($s, "\r", "\n"); // Mac

        // right trim
        $s = preg_replace("#[\t ]+$#m", '', $s); // right trim

        // trailing spaces
        $s = trim($s, "\n");

        return $s;
    }



    /**
     * Converts to web safe characters [a-z0-9-] text
     * @param string
     * @param string
     * @return string
     */
    function webalize($s, $charlist=NULL) /* static */
    {
        $s = TexyUtf::utf2ascii($s);
        $s = strtolower($s);
        if ($charlist) $charlist = preg_quote($charlist, '#');
        $s = preg_replace('#[^a-z0-9'.$charlist.']+#', '-', $s);
        $s = trim($s, '-');
        return $s;
    }



    /**
     * Texy! version of htmlSpecialChars (much faster than htmlSpecialChars!)
     * @param string
     * @return string
     */
    function escapeHtml($s) /* static */
    {
        return str_replace(array('&', '<', '>'), array('&amp;', '&lt;', '&gt;'), $s);
    }



    /**
     * Texy! version of html_entity_decode (always UTF-8, much faster than original!)
     * @param string
     * @return string
     */
    function unescapeHtml($s) /* static */
    {
        if (strpos($s, '&') === FALSE) return $s;

        if (PHP_VERSION >= 5) return html_entity_decode($s, ENT_QUOTES, 'UTF-8');

        static $entity=array('&AElig;'=>'&#198;','&Aacute;'=>'&#193;','&Acirc;'=>'&#194;','&Agrave;'=>'&#192;','&Alpha;'=>'&#913;','&Aring;'=>'&#197;','&Atilde;'=>'&#195;','&Auml;'=>'&#196;',
            '&Beta;'=>'&#914;','&Ccedil;'=>'&#199;','&Chi;'=>'&#935;','&Dagger;'=>'&#8225;','&Delta;'=>'&#916;','&ETH;'=>'&#208;','&Eacute;'=>'&#201;','&Ecirc;'=>'&#202;',
            '&Egrave;'=>'&#200;','&Epsilon;'=>'&#917;','&Eta;'=>'&#919;','&Euml;'=>'&#203;','&Gamma;'=>'&#915;','&Iacute;'=>'&#205;','&Icirc;'=>'&#206;','&Igrave;'=>'&#204;',
            '&Iota;'=>'&#921;','&Iuml;'=>'&#207;','&Kappa;'=>'&#922;','&Lambda;'=>'&#923;','&Mu;'=>'&#924;','&Ntilde;'=>'&#209;','&Nu;'=>'&#925;','&OElig;'=>'&#338;',
            '&Oacute;'=>'&#211;','&Ocirc;'=>'&#212;','&Ograve;'=>'&#210;','&Omega;'=>'&#937;','&Omicron;'=>'&#927;','&Oslash;'=>'&#216;','&Otilde;'=>'&#213;','&Ouml;'=>'&#214;',
            '&Phi;'=>'&#934;','&Pi;'=>'&#928;','&Prime;'=>'&#8243;','&Psi;'=>'&#936;','&Rho;'=>'&#929;','&Scaron;'=>'&#352;','&Sigma;'=>'&#931;','&THORN;'=>'&#222;',
            '&Tau;'=>'&#932;','&Theta;'=>'&#920;','&Uacute;'=>'&#218;','&Ucirc;'=>'&#219;','&Ugrave;'=>'&#217;','&Upsilon;'=>'&#933;','&Uuml;'=>'&#220;','&Xi;'=>'&#926;',
            '&Yacute;'=>'&#221;','&Yuml;'=>'&#376;','&Zeta;'=>'&#918;','&aacute;'=>'&#225;','&acirc;'=>'&#226;','&acute;'=>'&#180;','&aelig;'=>'&#230;','&agrave;'=>'&#224;',
            '&alefsym;'=>'&#8501;','&alpha;'=>'&#945;','&amp;'=>'&#38;','&and;'=>'&#8743;','&ang;'=>'&#8736;','&apos;'=>'&#39;','&aring;'=>'&#229;','&asymp;'=>'&#8776;',
            '&atilde;'=>'&#227;','&auml;'=>'&#228;','&bdquo;'=>'&#8222;','&beta;'=>'&#946;','&brvbar;'=>'&#166;','&bull;'=>'&#8226;','&cap;'=>'&#8745;','&ccedil;'=>'&#231;',
            '&cedil;'=>'&#184;','&cent;'=>'&#162;','&chi;'=>'&#967;','&circ;'=>'&#710;','&clubs;'=>'&#9827;','&cong;'=>'&#8773;','&copy;'=>'&#169;','&crarr;'=>'&#8629;',
            '&cup;'=>'&#8746;','&curren;'=>'&#164;','&dArr;'=>'&#8659;','&dagger;'=>'&#8224;','&darr;'=>'&#8595;','&deg;'=>'&#176;','&delta;'=>'&#948;','&diams;'=>'&#9830;',
            '&divide;'=>'&#247;','&eacute;'=>'&#233;','&ecirc;'=>'&#234;','&egrave;'=>'&#232;','&empty;'=>'&#8709;','&emsp;'=>'&#8195;','&ensp;'=>'&#8194;','&epsilon;'=>'&#949;',
            '&equiv;'=>'&#8801;','&eta;'=>'&#951;','&eth;'=>'&#240;','&euml;'=>'&#235;','&euro;'=>'&#8364;','&exist;'=>'&#8707;','&fnof;'=>'&#402;','&forall;'=>'&#8704;',
            '&frac12;'=>'&#189;','&frac14;'=>'&#188;','&frac34;'=>'&#190;','&frasl;'=>'&#8260;','&gamma;'=>'&#947;','&ge;'=>'&#8805;','&gt;'=>'&#62;','&hArr;'=>'&#8660;',
            '&harr;'=>'&#8596;','&hearts;'=>'&#9829;','&hellip;'=>'&#8230;','&iacute;'=>'&#237;','&icirc;'=>'&#238;','&iexcl;'=>'&#161;','&igrave;'=>'&#236;','&image;'=>'&#8465;',
            '&infin;'=>'&#8734;','&int;'=>'&#8747;','&iota;'=>'&#953;','&iquest;'=>'&#191;','&isin;'=>'&#8712;','&iuml;'=>'&#239;','&kappa;'=>'&#954;','&lArr;'=>'&#8656;',
            '&lambda;'=>'&#955;','&lang;'=>'&#9001;','&laquo;'=>'&#171;','&larr;'=>'&#8592;','&lceil;'=>'&#8968;','&ldquo;'=>'&#8220;','&le;'=>'&#8804;','&lfloor;'=>'&#8970;',
            '&lowast;'=>'&#8727;','&loz;'=>'&#9674;','&lrm;'=>'&#8206;','&lsaquo;'=>'&#8249;','&lsquo;'=>'&#8216;','&lt;'=>'&#60;','&macr;'=>'&#175;','&mdash;'=>'&#8212;',
            '&micro;'=>'&#181;','&middot;'=>'&#183;','&minus;'=>'&#8722;','&mu;'=>'&#956;','&nabla;'=>'&#8711;','&nbsp;'=>'&#160;','&ndash;'=>'&#8211;','&ne;'=>'&#8800;',
            '&ni;'=>'&#8715;','&not;'=>'&#172;','&notin;'=>'&#8713;','&nsub;'=>'&#8836;','&ntilde;'=>'&#241;','&nu;'=>'&#957;','&oacute;'=>'&#243;','&ocirc;'=>'&#244;',
            '&oelig;'=>'&#339;','&ograve;'=>'&#242;','&oline;'=>'&#8254;','&omega;'=>'&#969;','&omicron;'=>'&#959;','&oplus;'=>'&#8853;','&or;'=>'&#8744;','&ordf;'=>'&#170;',
            '&ordm;'=>'&#186;','&oslash;'=>'&#248;','&otilde;'=>'&#245;','&otimes;'=>'&#8855;','&ouml;'=>'&#246;','&para;'=>'&#182;','&part;'=>'&#8706;','&permil;'=>'&#8240;',
            '&perp;'=>'&#8869;','&phi;'=>'&#966;','&pi;'=>'&#960;','&piv;'=>'&#982;','&plusmn;'=>'&#177;','&pound;'=>'&#163;','&prime;'=>'&#8242;','&prod;'=>'&#8719;',
            '&prop;'=>'&#8733;','&psi;'=>'&#968;','&quot;'=>'&#34;','&rArr;'=>'&#8658;','&radic;'=>'&#8730;','&rang;'=>'&#9002;','&raquo;'=>'&#187;','&rarr;'=>'&#8594;',
            '&rceil;'=>'&#8969;','&rdquo;'=>'&#8221;','&real;'=>'&#8476;','&reg;'=>'&#174;','&rfloor;'=>'&#8971;','&rho;'=>'&#961;','&rlm;'=>'&#8207;','&rsaquo;'=>'&#8250;',
            '&rsquo;'=>'&#8217;','&sbquo;'=>'&#8218;','&scaron;'=>'&#353;','&sdot;'=>'&#8901;','&sect;'=>'&#167;','&shy;'=>'&#173;','&sigma;'=>'&#963;','&sigmaf;'=>'&#962;',
            '&sim;'=>'&#8764;','&spades;'=>'&#9824;','&sub;'=>'&#8834;','&sube;'=>'&#8838;','&sum;'=>'&#8721;','&sup1;'=>'&#185;','&sup2;'=>'&#178;','&sup3;'=>'&#179;',
            '&sup;'=>'&#8835;','&supe;'=>'&#8839;','&szlig;'=>'&#223;','&tau;'=>'&#964;','&there4;'=>'&#8756;','&theta;'=>'&#952;','&thetasym;'=>'&#977;','&thinsp;'=>'&#8201;',
            '&thorn;'=>'&#254;','&tilde;'=>'&#732;','&times;'=>'&#215;','&trade;'=>'&#8482;','&uArr;'=>'&#8657;','&uacute;'=>'&#250;','&uarr;'=>'&#8593;','&ucirc;'=>'&#251;',
            '&ugrave;'=>'&#249;','&uml;'=>'&#168;','&upsih;'=>'&#978;','&upsilon;'=>'&#965;','&uuml;'=>'&#252;','&weierp;'=>'&#8472;','&xi;'=>'&#958;','&yacute;'=>'&#253;',
            '&yen;'=>'&#165;','&yuml;'=>'&#255;','&zeta;'=>'&#950;','&zwj;'=>'&#8205;','&zwnj;'=>'&#8204;',
        );

        // named -> numeric
        $s = str_replace(array_keys($entity), array_values($entity), $s);

        // numeric -> unicode
        $s = preg_replace_callback(
            '#&(\\#x[0-9a-fA-F]+|\\#[0-9]+);#',
            array('Texy', '_entityCb'),
            $s
        );

        return $s;
    }


    /**
     * Callback for preg_replace_callback() in toText()
     *
     * @param array    matched entity
     * @return string  decoded entity
     */
    function _entityCb($matches)
    {
        list(, $entity) = $matches;

        $ord = ($entity{1} == 'x')
             ? hexdec(substr($entity, 2))
             : (int) substr($entity, 1);

        if ($ord<128)  // ASCII
            return chr($ord);

        if ($ord<2048) return chr(($ord>>6)+192) . chr(($ord&63)+128);
        if ($ord<65536) return chr(($ord>>12)+224) . chr((($ord>>6)&63)+128) . chr(($ord&63)+128);
        if ($ord<2097152) return chr(($ord>>18)+240) . chr((($ord>>12)&63)+128) . chr((($ord>>6)&63)+128) . chr(($ord&63)+128);
        return $match; // invalid entity
    }



    /**
     * Generate unique mark - useful for freezing (folding) some substrings
     * @param string   any string to froze
     * @param int      Texy::CONTENT_* constant
     * @return string  internal mark
     */
    function protect($child, $contentType=TEXY_CONTENT_BLOCK)
    {
        if ($child==='') return '';

        $key = $contentType
            . strtr(base_convert(count($this->marks), 10, 8), '01234567', "\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F")
            . $contentType;

        $this->marks[$key] = $child;

        return $key;
    }



    function unProtect($html)
    {
        return strtr($html, $this->marks);
    }



    /**
     * Filters bad URLs
     * @param string   user URL
     * @param string   type: a-anchor, i-image, c-cite
     * @return bool
     */
    function checkURL($URL, $type)
    {
        // absolute URL with scheme? check scheme!
        if (!empty($this->urlSchemeFilters[$type])
            && preg_match('#'.TEXY_URLSCHEME.'#iA', $URL)
            && !preg_match($this->urlSchemeFilters[$type], $URL))
            return FALSE;

        return TRUE;
    }



    /**
     * Is given URL relative?
     * @param string  URL
     * @return bool
     */
    function isRelative($URL) /* static */
    {
        // check for scheme, or absolute path, or absolute URL
        return !preg_match('#'.TEXY_URLSCHEME.'|[\#/?]#iA', $URL);
    }



    /**
     * Prepends root to URL, if possible
     * @param string  URL
     * @param string  root
     * @return string
     */
    function prependRoot($URL, $root) /* static */
    {
        if ($root == NULL || !Texy::isRelative($URL)) return $URL;
        return rtrim($root, '/\\') . '/' . $URL;
    }



    function getLinePatterns()
    {
        return $this->linePatterns;
    }



    function getBlockPatterns()
    {
        return $this->blockPatterns;
    }



    function getDOM()
    {
        return $this->DOM;
    }



    function tabCb($m) /* private */
    {
        return $m[1] . str_repeat(' ', $this->tabWidth - strlen($m[1]) % $this->tabWidth);
    }



    function free()
    {
        foreach (array_keys(get_object_vars($this)) as $key)
            $this->$key = NULL;
    }


    function __clone()
    {
        trigger_error('Clone is not supported.', E_USER_ERROR);
    }


    function Texy()  /* PHP 4 constructor */
    {
        // generate references (see http://www.dgx.cz/trine/item/how-to-emulate-php5-object-model-in-php4)
        foreach ($this as $key => $foo) $GLOBALS['$$HIDDEN$$'][] = & $this->$key;

        $args = func_get_args();
        call_user_func_array(array(&$this, '__construct'), $args);
    }

}
