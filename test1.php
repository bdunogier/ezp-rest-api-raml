<?php
$coveredBooks = ['content', 'content-types', 'user-management'];

$doc = new DOMDocument();
$doc->strictErrorChecking = FALSE;
$doc->loadHTML( file_get_contents( 'REST-API.html' ) );

$xpath = new DOMXPath( $doc );

$resources = [];

foreach (getBooks( $xpath ) as $book) {
    if ( !in_array( $book->getId(), $coveredBooks ) ) {
        continue;
    }
    // printf( "Book '%s' (#%s)\n", $book->getTitle(), $book->getId() );
    foreach ($book->getFeatures() as $feature) {
        if ( $feature->getResource() === 'n/a' ) {
            continue;
        }
        $resourceParts = explode( '/', trim( $feature->getResource(), '/' ) );
        $resourceLeaf = sprintf( "" );
        eval(
            $l = sprintf(
                "\$resources['/%s'] = [ 'method' => \$feature->getMethod(), 'description' => '\$feature->getDescription()' ];",
                implode( "']['/", $resourceParts )
             )
        );
    }
}

print_r( $resources );

/**
 * @return Book[]
 */
function getBooks( DOMXPath $xpath )
{
    $books = [];
    foreach ( $xpath->query( "//div[@class='section' and h1]" ) as $bookNode )
    {
        $book = new Book( $xpath, $bookNode );
        $books[$book->getId()] = $book;
    }
    return $books;
}

class Book
{
    /** @var \DOMXPath */
    private $xpath;

    /** @var \DOMNode */
    private $bookNode;

    public function __construct( DOMXPath $xpath, DOMNode $bookNode )
    {
        $this->xpath = $xpath;
        $this->bookNode = $bookNode;
    }

    /**
     * @return string
     */
    public function getId()
    {
        foreach( $this->bookNode->attributes as $attributeName => $attributeObject )
        {
            if ( $attributeName == 'id' )
            {
                return (string)$attributeObject->value;
            }
        }
        return null;
    }

    public function getTitle()
    {
        return $this->xpath->query( sprintf( "//div[@id='%s']/h1", $this->getId() ) )->item( 0 )->textContent;
    }

    /**
     * @return Feature[]
     */
    public function getFeatures()
    {
        $features = [];

        $xpathQuery = sprintf(
            "//div[@id='%s']//div[@class='section' and table and h4]",
            $this->getId()
        );
        foreach ( $this->xpath->query( $xpathQuery ) as $domNode)
        {
            $features[] = new Feature( $this->xpath, $domNode );
        }
        return $features;
    }
}


class Feature extends ElementBase
{
    public function getTitle()
    {
        $xpathQuery = sprintf(
            "//div[@id='%s']//node()[name()='h3' or name()='h4']",
            $this->getId()
        );
        return (string)$this->xpathNodeValue( $xpathQuery );
    }

    public function getId()
    {
        foreach( $this->getNode()->attributes as $attributeName => $attributeObject )
        {
            if ( $attributeName == 'id' )
            {
                return (string)$attributeObject->value;
            }
        }
        return null;
    }

    function getMethod()
    {
        $method = $this->getTableRowContent( 'Method:' );

        if ( strpos( $method, 'PATCH or POST' ) !== false ) {
            return 'patch';
        } else if ( strpos( $method, 'MOVE or POST' ) !== false ) {
            return 'move';
        } else if ( strpos( $method, 'COPY or POST' ) !== false ) {
            return 'copy';
        } else if ( strpos( $method, 'SWAP or POST' ) !== false ) {
            return 'swap';
        } else {
            return strtolower( $method );
        }
    }

    function getResource()
    {
        return $this->getTableRowContent( 'Resource:' );
    }

    function getDescription()
    {
        $description = $this->getTableRowContent( 'Description:' );
        $description = preg_replace( "/(\r\n|\r|\n)/", "", $description );
        $description = preg_replace( "/\s{2,}/m", " ", $description );

        return $description;
    }
}

abstract class ElementBase
{
    /** @var \DOMXPath */
    protected $xpath;

    /** @var \DOMNode */
    protected $node;

    public function __construct( DOMXPath $xpath, DOMNode $node )
    {
        $this->xpath = $xpath;
        $this->node = $node;
    }

    abstract protected function getId();

    protected function getTableRowContent( $headerRowContent )
    {
        $xpathQuery = sprintf(
            "//div[@id='%s']/table//tr[th/text()='%s']//td/p",
            $this->getId(), $headerRowContent
        );

        return $this->textNodeByXpath( $xpathQuery );
    }

    protected function textNodeByXpath( $xpathQuery )
    {
        return $this->xpathNodeValue(
            $this->xpathQuery( $xpathQuery )
        );
    }

    protected function xpathNodeValue( DOMNodeList $nodeList )
    {
        if ( $nodeList->length > 0 ) {
            return trim( (string)$nodeList->item(0)->nodeValue );
        } else {
            return 'n/a';
        }
    }

    protected function xpathQuery( $xpathQuery )
    {
        return $this->xpath->query( $xpathQuery );
    }

    protected function getNode()
    {
        return $this->node;
    }
}
