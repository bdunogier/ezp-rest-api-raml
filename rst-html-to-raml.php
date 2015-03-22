<?php
use Symfony\Component\Yaml\Dumper;

require 'vendor/autoload.php';

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

        addFeature( $resources, $feature );
    }

    // break;
}

$dumper = new Dumper();

$yaml = $dumper->dump($resources, 10);
echo $yaml;

// print_r( $resources );

function addFeature( &$resources, Feature $feature )
{
    $resourceParts = explode( '/', trim( $feature->getResource(), '/' ) );
    $resourceParts = array_map(
        function( $value ) { return "['/$value']"; },
        $resourceParts
    );
    $method = $feature->getMethod();
    $resourceProperties = ['description' => $feature->getDescription()];
    foreach ( $feature->getHeaders() as $headerName => $properties ) {
        $resourceProperties['headers'][$headerName] = $properties;
    }

    $leafVariable = '$resources' . implode( $resourceParts ) . "['$method']";

    eval( "$leafVariable = \$resourceProperties;" );
}

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
            // return 'move';
            return 'post';
        } else if ( strpos( $method, 'COPY or POST' ) !== false ) {
            // return 'copy';
            return 'post';
        } else if ( strpos( $method, 'SWAP or POST' ) !== false ) {
            // return 'swap';
            return 'post';
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
        return $this->getTableRowContent( 'Description:' );
    }

    /**
     * @return array
     */
    function getHeaders()
    {
        $headers = [];
        $xpathQuery = sprintf(
            "//div[@id='%s']/table//tr[th/text()='Headers:']/td/table/tbody/tr",
            $this->getId()
        );

        foreach ( $this->xpathQuery( $xpathQuery ) as $headerNode )
        {
            $headerProperties = [];

            $headerName = strtolower( trim( $this->xpathNodeValue( $this->xpath->query( './/th', $headerNode ) ), " :\t\n\r\0\x0B" ) );
            $headerTableNodes = $this->xpath->query( ".//table//tr/th", $headerNode );

            // si tr/td/table => liste de valeurs
            //   tr/td/table/tr/th => valeurs des en-têtes
            //   tr/td/table/tr/td => description des en-têtes
            if ( $headerTableNodes->length > 0 )
            {
                $headerValuesNodeList = $this->xpath->query( ".//tr/th", $headerNode );

                $headerProperties['enum'] = [];
                foreach ( $headerValuesNodeList as $headerValueNode ) {
                    $headerProperties['enum'][] = trim( $headerValueNode->nodeValue, " :\t\n\r\0\x0B" );
                }
            }
            //   tr/td/text() => description de l'en-têtes
            else
            {
                $headerProperties['description'] = trim( $this->xpathNodeValue( $this->xpath->query('.//td/p', $headerNode ) ), " :\t\n\r\0\x0B" );
            }

            $headers[$headerName] = $headerProperties;
        }
        return $headers;
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
            return $this->cleanupTextNodeValue( (string)$nodeList->item(0)->nodeValue );
        } else {
            return 'n/a';
        }
    }

    protected function cleanupTextNodeValue( $text)
    {
        $text = trim( $text, " :\t\n\r\0\x0B" );
        $text = preg_replace( "/(\r\n|\r|\n)/", "", $text );
        $text = preg_replace( "/\s{2,}/m", " ", $text );
        return $text;
    }


    protected function xpathQuery( $xpathQuery )
    {
        return $this->xpath->query( $xpathQuery, $this->node );
    }

    protected function getNode()
    {
        return $this->node;
    }
}

class Headers extends ElementBase
{
    protected function getId()
    {
        // TODO: Implement getId() method.
    }
}
