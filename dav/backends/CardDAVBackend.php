<?php

namespace Sabre\CardDAV\Backend;

use Sabre\CardDAV;
use Sabre\DAV;

/**
 * PDO CardDAV backend
 *
 * This CardDAV backend uses PDO to store addressbooks
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class ChurchTools extends AbstractBackend {

    /**
     * PDO connection
     *
     * @var PDO
     */
    protected $pdo;

    /**
     * The PDO table name used to store cards
     */
    protected $cardsTableName = 'cdb_person';

    /**
     * Sets up the object
     *
     * @param \PDO $pdo
     * @param string $addressBooksTableName
     * @param string $cardsTableName
     */
    public function __construct(\PDO $pdo) {

        $this->pdo = $pdo;

    }

    /**
     * Returns the list of addressbooks for a specific user.
     *
     * @param string $principalUri
     * @return array
     */
    public function getAddressBooksForUser($principalUri) {
        // get the user login from $principalUri
        $tmp = explode('/', $principalUri);
        $user = $tmp[count($tmp)-1];

        $addressBooks = array();
        $addressBooks[] = [
            'id'                                                          => $user,
            'uri'                                                         => 'churchtools',
            'principaluri'                                                => 'principals/'.$user,
            '{DAV:}displayname'                                           => $user,
            '{' . CardDAV\Plugin::NS_CARDDAV . '}addressbook-description' => 'Default addressbook for user '.$user,
            '{http://calendarserver.org/ns/}getctag'                      => md5($user),
            '{http://sabredav.org/ns}sync-token'                          => md5($user) ? md5($user) : '0',
        ];

        return $addressBooks;
    }

    /**
    * Get a user id for a kOOL login name
    */
    protected function getKoolUserId($loginName) {
        $stmt = $this->pdo->prepare('SELECT id FROM cdb_person WHERE email = ?');
        $stmt->execute(array($loginName));
        $result = $stmt->fetchAll();
        if (!count($result)) return;
        return $result[0]['id'];
    }

    /**
     * Updates properties for an address book.
     *
     * The list of mutations is stored in a Sabre\DAV\PropPatch object.
     * To do the actual updates, you must tell this object which properties
     * you're going to process with the handle() method.
     *
     * Calling the handle method is like telling the PropPatch object "I
     * promise I can handle updating this property".
     *
     * Read the PropPatch documenation for more info and examples.
     *
     * @param string $addressBookId
     * @param \Sabre\DAV\PropPatch $propPatch
     * @return void
     */
    function updateAddressBook($addressBookId, \Sabre\DAV\PropPatch $propPatch) {}

    /**
     * Creates a new address book
     *
     * @param string $principalUri
     * @param string $url Just the 'basename' of the url.
     * @param array $properties
     * @return void
     */
    public function createAddressBook($principalUri, $url, array $properties) {
    }


    private function getLastModified($person) {
        $stmt = $this->pdo->prepare('SELECT letzteaenderung FROM cdb_person WHERE id = ?');
        $stmt->execute(array($person['id']));
        $result = $stmt->fetchAll();
        if (!count($result)) return 0;
        return strtotime($result[0]['date']);
    }

    /**
     * Deletes an entire addressbook and all its contents
     *
     * @param int $addressBookId
     * @return void
     */
    public function deleteAddressBook($addressBookId) {
    }

    /**
    * own method
    */
    protected function retrieveAddresses($addressbookId, $id=null) {        // get admin filters:
        $select = 'SELECT id, cmsuserid, vorname, name, email, telefonhandy, plz, strasse, ort from cdb_person where cmsuserid != ?';
        if ($id) $select .= 'AND (id='.$id.') ';

        $stmt = $this->pdo->prepare($select);
        $stmt->execute(array($addressbookId));
        $result = $stmt->fetchAll();

        if ($id && $result[0]) $result = $result[0];

        return $result;
    }

    /**
     * Returns all cards for a specific addressbook id.
     *
     * This method should return the following properties for each card:
     *   * carddata - raw vcard data
     *   * uri - Some unique url
     *   * lastmodified - A unix timestamp
     *
     * It's recommended to also return the following properties:
     *   * etag - A unique etag. This must change every time the card changes.
     *   * size - The size of the card in bytes.
     *
     * If these last two properties are provided, less time will be spent
     * calculating them. If they are specified, you can also ommit carddata.
     * This may speed up certain requests, especially with large cards.
     *
     * @param mixed $addressbookId
     * @return array
     */
    public function getCards($addressbookId) {
        // we completely ignore $addressbookId here, since there is always only ONE
        // addressbook available (the one from kOOL).
        $p = $this->retrieveAddresses($addressbookId);

        $o = array();

        $_SESSION['ses_userid'] = $this->getKoolUserId($addressbookId);
        //die (print_r($_SESSION));



        foreach ($p as $person) {
            $mod = $this->getLastModified($person);
            if (!$mod) $mod = strtotime($person['crdate']);

            $card = new \Peregrinus\CardDAV\vCard();
            $card->addPerson($person);

            $o[] = array(
                'carddata' => $card->output,
                'uri' => $person['id'],
                'lastmodified' => $mod,
                'etag' => md5($person),
            );
        }

            //die('<pre>'.print_r($p, true));
        return $o;

    }

    /**
     * Returns a specfic card.
     *
     * The same set of properties must be returned as with getCards. The only
     * exception is that 'carddata' is absolutely required.
     *
     * @param mixed $addressBookId
     * @param string $cardUri
     * @return array
     */
    public function getCard($addressBookId, $cardUri) {
        $person = $this->retrieveAddresses($addressBookId, $cardUri);
        $mod = $this->getLastModified($person);
        if (!$mod) $mod = strtotime($person['crdate']);

        $_SESSION['ses_userid'] = $this->getKoolUserId($addressBookId);
        //die (print_r($_SESSION));

        $card = new \Peregrinus\CardDAV\vCard();
        $card->addPerson($person);

        $o = array(
            'carddata' => $card->output,
            'uri' => $person['id'],
            'lastmodified' => $mod,
            'etag' => md5($person),
        );

        return $o;
    }

    /**
     * Creates a new card.
     *
     * The addressbook id will be passed as the first argument. This is the
     * same id as it is returned from the getAddressbooksForUser method.
     *
     * The cardUri is a base uri, and doesn't include the full path. The
     * cardData argument is the vcard body, and is passed as a string.
     *
     * It is possible to return an ETag from this method. This ETag is for the
     * newly created resource, and must be enclosed with double quotes (that
     * is, the string itself must contain the double quotes).
     *
     * You should only return the ETag if you store the carddata as-is. If a
     * subsequent GET request on the same card does not have the same body,
     * byte-by-byte and you did return an ETag here, clients tend to get
     * confused.
     *
     * If you don't return an ETag, you can just return null.
     *
     * @param mixed $addressBookId
     * @param string $cardUri
     * @param string $cardData
     * @return string|null
     */
    public function createCard($addressBookId, $cardUri, $cardData) {
    }

    /**
     * Updates a card.
     *
     * The addressbook id will be passed as the first argument. This is the
     * same id as it is returned from the getAddressbooksForUser method.
     *
     * The cardUri is a base uri, and doesn't include the full path. The
     * cardData argument is the vcard body, and is passed as a string.
     *
     * It is possible to return an ETag from this method. This ETag should
     * match that of the updated resource, and must be enclosed with double
     * quotes (that is: the string itself must contain the actual quotes).
     *
     * You should only return the ETag if you store the carddata as-is. If a
     * subsequent GET request on the same card does not have the same body,
     * byte-by-byte and you did return an ETag here, clients tend to get
     * confused.
     *
     * If you don't return an ETag, you can just return null.
     *
     * @param mixed $addressBookId
     * @param string $cardUri
     * @param string $cardData
     * @return string|null
     */
    public function updateCard($addressBookId, $cardUri, $cardData) {
    }

    /**
     * Deletes a card
     *
     * @param mixed $addressBookId
     * @param string $cardUri
     * @return bool
     */
    public function deleteCard($addressBookId, $cardUri) {
    }
}
