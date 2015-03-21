<?php
$coveredBooks = ['content', 'content-types', 'user-management'];

$doc = new DOMDocument();
$doc->strictErrorChecking = FALSE;
$doc->loadHTML( file_get_contents( 'REST-API.html' ) );

$xpath = new DOMXPath( $doc );

foreach (getBooks( $xpath ) as $book) {
    if ( !in_array( $book->getId(), $coveredBooks ) ) {
        continue;
    }
    printf( "- %s (#%s)\n", $book->getTitle(), $book->getId() );
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

    }
}


class Feature
{
    public function getTitle()
    {

    }

    public function getId()
    {

    }
}
