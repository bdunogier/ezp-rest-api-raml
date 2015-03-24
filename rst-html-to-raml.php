<?php
use Symfony\Component\Yaml\Dumper as YamlDumper;
use Symfony\Component\Yaml\Parser as YamlParser;

require 'vendor/autoload.php';

$coveredBooks = ['content', 'content-types', 'user-management'];

$doc = new DOMDocument();
$doc->strictErrorChecking = FALSE;
$doc->loadHTML( file_get_contents( 'REST-API.html' ) );

$xpath = new DOMXPath( $doc );

$ramlArray = [];

foreach (getBooks( $xpath ) as $book) {
    if ( !in_array( $book->getId(), $coveredBooks ) ) {
        continue;
    }
    foreach ($book->getFeatures() as $feature) {
        if ( $feature->getResource() === 'n/a' ) {
            continue;
        }

        addFeature( $ramlArray, $feature );
    }
}

$dumper = new YamlDumper();

$yaml = $dumper->dump($ramlArray, 10);
echo file_get_contents( 'ezp-rest-api-template.raml' );
echo $yaml;

function addFeature( &$resources, Feature $feature )
{
    $resource = $feature->getResource();
    $resourceParts = explode( '/', trim( $resource, '/' ) );
    $resourceParts = array_map(
        function( $value ) { return "['/$value']"; },
        $resourceParts
    );
    $method = $feature->getMethod();
    $resourceProperties = ['description' => $feature->getDescription()];
    $resourceProperties['headers'] = $feature->getHeaders();

    $resourceProperties['responses'] = $feature->getErrorCodes();
    if ( $successCode = $feature->getSuccessResponseCode() )
        $resourceProperties['responses'][$successCode] = ['description' => 'success'];

    // add request body content types
    if ( isset( $resourceProperties['headers']['content-type']['enum'] ) ) {
        foreach ( $resourceProperties['headers']['content-type']['enum'] as $contentTypeHeader ) {
            $resourceProperties['body'][$contentTypeHeader] = getSchema( $contentTypeHeader );
        }
    }

    // add response body content types upon success
    if ( $successCode && isset( $resourceProperties['headers']['accept']['enum'] ) ) {
        foreach ( $resourceProperties['headers']['accept']['enum'] as $acceptHeader ) {
            $resourceProperties['responses'][$successCode]['body'][$acceptHeader] = getSchema( $acceptHeader );
        }
    }

    $queryParameters = $feature->getQueryParameters();
    if ( count( $queryParameters ) ) {
        $resourceProperties['queryParameters'] = $queryParameters;
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
        $method = strtolower( $this->getTableRowContent( 'Method:' ) );

        if ( strpos( $method, 'patch or post' ) !== false ) {
            $method = 'patch';
        } else if ( strpos( $method, 'get (not implemented)' ) !== false ) {
            $method = 'get';
        } else if ( strpos( $method, 'publish or post' ) !== false ) {
            // return 'publish';
            $method = 'post';
        } else if ( strpos( $method, 'move or post' ) !== false ) {
            // return 'move';
            $method = 'post';
        } else if ( strpos( $method, 'copy or post' ) !== false ) {
            // return 'copy';
            $method = 'post';
        } else if ( strpos( $method, 'swap or post' ) !== false ) {
            // return 'swap';
            $method = 'post';
        }

        return $method;
    }

    function getResource()
    {
        $resource = $this->getTableRowContent( 'Resource:' );
        $resource = str_replace( '/content/objects/<ID>', '/content/objects/{contentId}', $resource );
        $resource = str_replace( '/versions/<no>', '/versions/{versionNo}', $resource );
        $resource = str_replace( '/versions/<versionNo>', '/versions/{versionNo}', $resource );
        $resource = str_replace( '<imageId>', '{imageId}', $resource );
        $resource = str_replace( '<variationIdentifier>', '{variationIdentifier}', $resource );
        $resource = str_replace( '/locations/<path>', '/locations/{locationPath}', $resource );
        $resource = str_replace( '/locations/<ID>', '/locations/{locationId}', $resource );
        $resource = str_replace( '/views/<identifier>', '/views/{viewIdentifier}', $resource );
        $resource = str_replace( '/sections/<ID>', '/sections/{sectionId}', $resource );
        $resource = str_replace( '/trash/<ID>', '/trash/{trashId}', $resource );
        $resource = str_replace( '/objectstategroups/<ID>', '/objectstategroups/{objectStateGroupId}', $resource );
        $resource = str_replace( '/objectstates/<ID>', '/objectstates/{objectStateId}', $resource );
        $resource = str_replace( '/urlaliases/<ID>', '/urlaliases/{urlAliasId}', $resource );
        $resource = str_replace( '/urlwildcards/<ID>', '/urlwildcards/{urlWildcardId}', $resource );
        $resource = str_replace( '/typegroups/<ID>', '/typegroups/{contentTypeGroupId}', $resource );
        $resource = str_replace( '/type/<ID>', '/type/{contentTypeId}', $resource );
        $resource = str_replace( '/groups/<ID>', '/groups/{contentTypeGroupId}', $resource );
        $resource = str_replace( '/groups/<path>', '/groups/{userGroupPath}', $resource );
        $resource = str_replace( '/users/<ID>', '/users/{userId}', $resource );
        $resource = str_replace( '/roles/<ID>', '/roles/{roleId}', $resource );
        $resource = str_replace( '/policies/<ID>', '/policies/{policyId}', $resource );
        $resource = str_replace( '/<sessionID>', '/{sessionId}', $resource );
        $resource = str_replace( '/relations/<ID>', '/relations/{relationId}', $resource );

        return $resource;
    }

    function getDescription()
    {
        return $this->getTableRowContent( 'Description:' );
    }

    function getQueryParameters()
    {
        $parameters = [];
        $xpathQuery = sprintf(
            "//div[@id='%s']/table//tr[th/text()='Parameters:']/td/table/tbody/tr",
            $this->getId()
        );

        foreach ( $this->xpathQuery( $xpathQuery ) as $parameterNode ) {
            $parameterName = $this->xpathNodeValue( $this->xpath->query( './/th', $parameterNode ) );
            $parameterDescription = $this->xpathNodeValue( $this->xpath->query( './/td', $parameterNode ) );
            $parameters[$parameterName] = ['description' => $parameterDescription];
        }

        return $parameters;
    }

    function getSuccessResponseCode()
    {
        $xpathQuery = sprintf(
            "//div[@id='%s']/pre[@class='code http literal-block']",
            $this->getId()
        );

        $nodeList = $this->xpath->query( $xpathQuery, $this->node );
        if ( $nodeList->length === 0 ) {
            return 200;
        }

        foreach ( $this->xpath->query(".//span[@class='literal number' and number(text()) = text()]", $nodeList->item( 0 ) ) as $node ) {
            if ( $node->nodeValue == "1.1" )
                continue;
            $code = $node->nodeValue;
            break;
        }

        return $code;
    }

    function getErrorCodes()
    {
        $responses = [];
        $xpathQuery = sprintf(
            "//div[@id='%s']/table//tr[th/text()='Error Codes:' or th/text()='Error codes:']/td/table/tbody/tr",
            $this->getId()
        );

        foreach ( $this->xpathQuery( $xpathQuery ) as $errorCodeNode ) {
            $errorCodeValue = $this->xpathNodeValue( $this->xpath->query( './/th', $errorCodeNode ) );
            $errorCodeDescription = $this->xpathNodeValue( $this->xpath->query( './/td', $errorCodeNode ) );
            $responses[$errorCodeValue] = ['description' => $errorCodeDescription];
        }

        return $responses;
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
            "//div[@id='%s']/table//tr[th/text()='%s']//td/p|//div[@id='%s']/table//tr[th/text()='%s']//td",
            $this->getId(), $headerRowContent, $this->getId(), $headerRowContent
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

function getSchema( $contentType )
{
    list( $mediaType, $type,) = explode( '+', $contentType );
    if (
        $type === 'xml' &&
        sscanf( $mediaType, 'application/vnd.ez.api.%s', $type ) &&
        file_exists( "schemas/xsd/$type.xsd" )
    ) {
        return ['schema' => $type];
    }

    return null;
}
