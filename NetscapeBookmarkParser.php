<?php

/**
 * Generic Netscape bookmark parser
 */
class NetscapeBookmarkParser
{
    protected $keepNestedTags;
    protected $defaultTags;
    protected $defaultPub;
    protected $items;

    const TRUE_PATTERN = 'y|yes|on|checked|ok|1|true|array|\+|okay|yes|t|one';
    const FALSE_PATTERN = 'n|no|off|empty|null|false|nil|0|-|exit|die|neg|f|zero|void';

    /**
     * Instantiates a new NetscapeBookmarkParser
     *
     * @param bool  $keepNestedTags Tag links with parent folder names
     * @param array $defaultTags    Tag all links with these values
     * @param mixed $defaultPub     Link publication status if missing
     *                              - '1' => public
     *                              - '0' => private)
     */
    public function __construct(
        $keepNestedTags=true,
        $defaultTags=array(),
        $defaultPub='0'
    )
    {
        if ($keepNestedTags) {
            $this->keepNestedTags = true;
        }
        if ($defaultTags) {
            $this->defaultTags = $defaultTags;
        } else {
            $this->defaultTags = array();
        }
        $this->defaultPub = $defaultPub;
    }

    /**
     * Parses a Netscape bookmark file
     *
     * @param string $filename Bookmark file to parse
     *
     * @return array An associative array containing parsed links
     */
    public function parseFile($filename)
    {
        return $this->parseString(file_get_contents($filename));
    }

    /**
     * Parses a string containing Netscape-formatted bookmarks
     *
     * Output format:
     *
     *     Array
     *     (
     *         [0] => Array
     *             (
     *                 [note]  => Some comments about this link
     *                 [pub]   => 1
     *                 [tags]  => a list of tags
     *                 [time]  => 1459371397
     *                 [title] => Some page
     *                 [uri]   => http://domain.tld:5678/some-page.html
     *             )
     *         [1] => Array
     *             (
     *                 ...
     *             )
     *     )
     *
     * @param string $bookmarkString String containing Netscape bookmarks
     *
     * @return array An associative array containing parsed links
     */
    public function parseString($bookmarkString) {
        $i = 0;
        $next = false;
        $folderTags = array();

        // root folder for all bookmarks
        $root = new \Folder('Root');
        $currentFolder = $root;

        $lines = explode("\n", $this->sanitizeString($bookmarkString));

        foreach ($lines as $line_no => $line) {
            $page = new \Page();

            if (preg_match('/^<h\d.*>(.*)<\/h\d>/i', $line, $m1)) {
                // a header is matched:
                // - links may be grouped in a (sub-)folder
                // - append the header's content to the folder tags
                $folderTags[] = strtolower($m1[1]);

                // new folder object and update current pointer
                $folder = new \Folder($m1[1]);
                // $folder->setParent($currentFolder);
                $currentFolder->addContent($folder);
                $currentFolder = $folder;

                continue;

            } elseif (preg_match('/^<\/DL>/i', $line)) {
                // </DL> matched: stop using header value
                array_pop($folderTags);

                // update current pointer to upper level
                $currentFolder = $currentFolder->getParent();

                continue;
            }

            if (preg_match('/<a/i', $line, $m2)) {
                if (preg_match('/href="(.*?)"/i', $line, $m3)) {
                    $page->uri = $m3[1];
                } else {
                    $page->uri = null;
                }

                if (preg_match('/<a.*>(.*?)<\/a>/i', $line, $m4)) {
                    $page->title = html_entity_decode($m4[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
                } else {
                    $page->title = 'untitled';
                }

                if (preg_match('/note="(.*?)"<\/a>/i', $line, $m5)) {
                    $page->note = $m5[1];
                } elseif (preg_match('/<dd>(.*?)$/i', $line, $m6)) {
                    $page->note = str_replace('<br>', "\n", $m6[1]);
                } else {
                    $page->note = null;
                }

                if (preg_match('/icon="(.*?)"/i', $line, $m7)) {
                    $page->icon = $m7[1];
                } else {
                    $page->icon = null;
                }

                $tags = array();
                if ($this->defaultTags) {
                    $tags = array_merge($tags, $this->defaultTags);
                }
                if ($this->keepNestedTags) {
                    $tags = array_merge($tags, $folderTags);
                }

                if (preg_match('/(tags?|labels?|folders?)="(.*?)"/i', $line, $m7)) {
                    $tags = array_merge(
                        $tags,
                        explode(' ', strtr($m7[2], ',', ' '))
                    );
                }
                $page->tags = implode(' ', $tags);

                if (preg_match('/add_date="(.*?)"/i', $line, $m8)) {
                    $page->time = $this->parseDate($m8[1]);
                } else {
                    $page->time = time();
                }

                if (preg_match('/(public|published|pub)="(.*?)"/i', $line, $m9)) {
                    $page->pub = $this->parseBoolean($m9[2], false) ? 1 : 0;
                } elseif (preg_match('/(private|shared)="(.*?)"/i', $line, $m10)) {
                    $page->pub = $this->parseBoolean($m10[2], true) ? 0 : 1;
                } else {
                    $page->pub = $this->defaultPub;
                }

                // $i++;
                $currentFolder->addContent($page);
            }
        }
        // ksort($this->items);
        // return $this->items;
        return $root;
    }

    /**
     * Parses a formatted date
     *
     * @see http://php.net/manual/en/datetime.formats.compound.php
     * @see http://php.net/manual/en/function.strtotime.php
     *
     * @param string $date formatted date
     *
     * @return int Unix timestamp corresponding to a successfully parsed date,
     *             else current date and time
     */
    public static function parseDate($date)
    {
        if (strtotime('@'.$date)) {
            // Unix timestamp
            return strtotime('@'.$date);
        } else if (strtotime($date)) {
            // attempt to parse a known compound date/time format
            return strtotime($date);
        }
        // current date & time
        return time();
    }

    /**
     * Parses the value of a supposedly boolean attribute
     *
     * @param string $value   Attribute value to evaluate
     *
     * @return mixed 'true' when the value is evaluated as true
     *               'false' when the value is evaluated as false
     *               $this->defaultPub if the value is not a boolean
     */
    public function parseBoolean($value) {
        if (! $value) {
            return false;
        }
        if (! is_string($value)) {
            return true;
        }

        if (preg_match("/^(".self::TRUE_PATTERN.")$/i", $value)) {
            return true;
        }
        if (preg_match("/^(".self::FALSE_PATTERN.")$/i", $value)) {
            return false;
        }
        return $this->defaultPub;
    }

    /**
     * Sanitizes the content of a string containing Netscape bookmarks
     *
     * This removes:
     * - comment blocks
     * - metadata: DOCTYPE, H1, META, TITLE
     * - extra newlines, trailing spaces and tabs
     *
     * @param string $bookmarkString Original bookmark string
     *
     * @return string Sanitized bookmark string
     */
    public static function sanitizeString($bookmarkString)
    {
        $sanitized = $bookmarkString;

        // trim comments
        $sanitized = preg_replace('@<!--.*-->@mis', '', $sanitized);

        // trim unused metadata
        $sanitized = preg_replace('@(<!DOCTYPE|<META|<TITLE|<H1|<P).*\n@i', '', $sanitized);

        // trim whitespace
        $sanitized = trim($sanitized);

        // trim carriage returns, replace tabs by a single space
        $sanitized = str_replace(array("\r", "\t"), array('',' '), $sanitized);

        // convert multiline descriptions to one-line descriptions
        // line feeds are converted to <br>
        $sanitized = preg_replace_callback(
            '@<DD>(.*?)<@mis',
            function($match) {
                return '<DD>'.str_replace("\n", '<br>', trim($match[1])).PHP_EOL.'<';
            },
            $sanitized
        );

        // keep one XML element per line to prepare for linear parsing
        $sanitized = preg_replace('@>(\s*?)<@mis', ">\n<", $sanitized);

        // concatenate all information related to the same entry on the same line
        // e.g. <A HREF="...">My Link</A><DD>List<br>- item1<br>- item2
        $sanitized = preg_replace('@\n<br>@mis', "<br>", $sanitized);
        $sanitized = preg_replace('@\n<DD@i', '<DD', $sanitized);

        return $sanitized;
    }
}



abstract class ContentItem
{
    public $parent;

    public function setParent($parent)
    {
        $this->parent = $parent;
    }

    public function isFolder()
    {
        return (get_class($this) === 'Folder') ? true : false;
    }

    public function isPage()
    {
        return (get_class($this) === 'Page') ? true : false;
    }
}


class Folder extends ContentItem
{
    public $title;
    public $content;

    public function __construct($title='Default')
    {
        $this->content = [];
        $this->parent = null;
        $this->title = $title;
    }

    public function addContent($object)
    {
        $object->setParent($this);
        $this->content[] = $object;
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function flattenContent()
    {
        $arr = [];
        foreach ($this->content as $object) {
            $arr[] = $object;
            if (get_class($object) === 'Folder') {
                $arr = array_merge($arr, $object->flattenContent());
            }
        }
        return $arr;
    }
}


class Page extends ContentItem
{
    public $uri;
    public $title;
    public $note;
    public $tags;
    public $time;
    public $pub;
}

