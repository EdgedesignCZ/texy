<?php

/**
 * This file is part of the Texy! formatter (http://texy.info/)
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004-2007 David Grudl aka -dgx- (http://www.dgx.cz)
 * @license    GNU GENERAL PUBLIC LICENSE version 2 or 3
 * @version    $Revision$ $Date$
 * @category   Text
 * @package    Texy
 */

// security - include texy.php, not this file
if (!class_exists('Texy', FALSE)) die();



// for PHP 4 backward compatibility
define('TEXY_HEADING_FIXED',  TexyHeadingModule::FIXED);
define('TEXY_HEADING_DYNAMIC',  TexyHeadingModule::DYNAMIC);


/**
 * Heading module
 */
final class TexyHeadingModule extends TexyModule
{
    const
        DYNAMIC = 1,  // auto-leveling
        FIXED = 2;  // fixed-leveling

    /** @var string  textual content of first heading */
    public $title;

    /** @var array  generated Table of Contents */
    public $TOC;

    /** @var bool  autogenerate ID */
    public $generateID = FALSE;

    /** @var string  prefix for autogenerated ID */
    public $idPrefix = 'toc-';

    /** @var int  level of top heading, 1..6 */
    public $top = 1;

    /** @var int  balancing mode */
    public $balancing = TexyHeadingModule::DYNAMIC;

    /** @var array  when $balancing = TexyHeadingModule::FIXED */
    public $levels = array(
        '#' => 0,  //  #  -->  $levels['#'] + $top = 0 + 1 = 1  --> <h1> ... </h1>
        '*' => 1,
        '=' => 2,
        '-' => 3,
    );

    /** @var array  used ID's */
    private $usedID;

    /** @var array */
    private $dynamicMap;

    /** @var int */
    private $dynamicTop;



    public function __construct($texy)
    {
        parent::__construct($texy);

        $texy->addHandler('heading', array($this, 'solve'));

        $texy->registerBlockPattern(
            array($this, 'patternUnderline'),
            '#^(\S.*)'.TEXY_MODIFIER_H.'?\n'
          . '(\#{3,}|\*{3,}|={3,}|-{3,})$#mU',
            'heading/underlined'
        );

        $texy->registerBlockPattern(
            array($this, 'patternSurround'),
            '#^(\#{2,}+|={2,}+)(.+)'.TEXY_MODIFIER_H.'?()$#mU',
            'heading/surrounded'
        );
    }


    public function begin()
    {
        $this->title = NULL;
        $this->usedID = array();

        // clear references
        $this->TOC = array();
        $foo1 = array(); $this->dynamicMap = & $foo1;
        $foo2 = -100; $this->dynamicTop = & $foo2;
    }



    /**
     * Callback for underlined heading
     *
     *  Heading .(title)[class]{style}>
     *  -------------------------------
     *
     * @param TexyBlockParser
     * @param array      regexp matches
     * @param string     pattern name
     * @return TexyHtml|string|FALSE
     */
    public function patternUnderline($parser, $matches)
    {
        list(, $mContent, $mMod, $mLine) = $matches;
        //  $matches:
        //    [1] => ...
        //    [2] => .(title)[class]{style}<>
        //    [3] => ...

        $mod = new TexyModifier($mMod);
        $level = $this->levels[$mLine[0]];
        return $this->texy->invokeHandlers('heading', $parser, array($level, $mContent, $mod, FALSE));
    }



    /**
     * Callback for surrounded heading
     *
     *   ### Heading .(title)[class]{style}>
     *
     * @param TexyBlockParser
     * @param array      regexp matches
     * @param string     pattern name
     * @return TexyHtml|string|FALSE
     */
    public function patternSurround($parser, $matches)
    {
        list(, $mLine, $mContent, $mMod) = $matches;
        //    [1] => ###
        //    [2] => ...
        //    [3] => .(title)[class]{style}<>

        $mod = new TexyModifier($mMod);
        $level = 7 - min(7, max(2, strlen($mLine)));
        $mContent = rtrim($mContent, $mLine[0] . ' ');
        return $this->texy->invokeHandlers('heading', $parser, array($level, $mContent, $mod, TRUE));
    }



    /**
     * Finish invocation
     *
     * @param TexyHandlerInvocation  handler invocation
     * @param int
     * @param string
     * @param TexyModifier
     * @param bool
     * @return TexyHtml
     */
    public function solve($invocation, $level, $content, $mod, $isSurrounded)
    {
        $tx = $this->texy;
        $el = new TexyHeadingElement;
        $mod->decorate($tx, $el);

        $el->_level = $level;
        $el->_top = $this->top;

        if ($this->balancing === self::DYNAMIC) {
            if ($isSurrounded) {
                $this->dynamicTop = max($this->dynamicTop, $this->top - $level);
                $el->_top = & $this->dynamicTop;
            } else {
                $this->dynamicMap[$level] = $level;
                $el->_map = & $this->dynamicMap;
            }
        }
        $el->parseLine($tx, trim($content));

        // document title
        $title = $el->toText($tx);
        if ($this->title === NULL) $this->title = $title;

        // Table of Contents
        if ($this->generateID && empty($el->attrs['id'])) {
            $id = $this->idPrefix . Texy::webalize($title);
            $counter = '';
            if (isset($this->usedID[$id . $counter])) {
                $counter = 2;
                while (isset($this->usedID[$id . '-' . $counter])) $counter++;
                $id .= '-' . $counter;
            }
            $this->usedID[$id] = TRUE;
            $el->attrs['id'] = $id;
        }

        $TOC = array(
            'id' => isset($el->attrs['id']) ? $el->attrs['id'] : NULL,
            'title' => $title,
            'level' => 0,
        );
        $this->TOC[] = & $TOC;
        $el->_TOC = & $TOC;

        return $el;
    }


}







/**
 * HTML ELEMENT H1-6
 */
class TexyHeadingElement extends TexyHtml
{
    public $_level;
    public $_top;
    public $_map;
    public $_TOC;


    public function startTag()
    {
        $level = $this->_level;

        if ($this->_map) {
            asort($this->_map);
            $level = array_search($level, array_values($this->_map), TRUE);
        }

        $level += $this->_top;

        $this->setName('h' . min(6, max(1, $level)));
        $this->_TOC['level'] = $level;
        return parent::startTag();
    }

}
