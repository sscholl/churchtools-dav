<?php

namespace Sabre\DAVACL\PrincipalBackend;

use Sabre\DAV;
use Sabre\DAV\MkCol;
use Sabre\HTTP\URLUtil;

/**
 * ChurchTools principal backend
 *
 *
 * This backend assumes all principals are in a single collection. The default collection
 * is 'principals/', but this can be overriden.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class ChurchTools extends AbstractBackend {

    /**
     * PDO table name for 'principals'
     *
     * @var string
     */
    public $tableName = 'cdb_person';

    /**
     * PDO table name for 'group members'
     *
     * @var string
     */
    public $groupMembersTableName = 'groupmembers';

    /**
     * pdo
     *
     * @var PDO
     */
    protected $pdo;

    /**
     * A list of additional fields to support
     *
     * @var array
     */
    protected $fieldMap = [

        /**
         * This property can be used to display the users' real name.
         */
        '{DAV:}displayname' => [
            'dbField' => 'email',
        ],

        /**
         * This is the users' primary email-address.
         */
        '{http://sabredav.org/ns}email-address' => [
            'dbField' => 'email',
        ],
    ];

    /**
     * Sets up the backend.
     *
     * @param PDO $pdo
     */
    function __construct(\PDO $pdo) {

        $this->pdo = $pdo;

    }


    /**
     * Returns a list of principals based on a prefix.
     *
     * This prefix will often contain something like 'principals'. You are only
     * expected to return principals that are in this base path.
     *
     * You are expected to return at least a 'uri' for every user, you can
     * return any additional properties if you wish so. Common properties are:
     *   {DAV:}displayname
     *   {http://sabredav.org/ns}email-address - This is a custom SabreDAV
     *     field that's actualy injected in a number of other properties. If
     *     you have an email address, use this property.
     *
     * @param string $prefixPath
     * @return array
     */
    public function getPrincipalsByPrefix($prefixPath) {

        $fields = array(
            'id',
        );

        foreach($this->fieldMap as $key=>$value) {
            $fields[] = $value['dbField'];
        }
        $result = $this->pdo->query('SELECT '.implode(',', $fields).'  FROM '. $this->tableName);

        $principals = array();

        while($row = $result->fetch(\PDO::FETCH_ASSOC)) {

            // Checking if the principal is in the prefix
            list($rowPrefix) = URLUtil::splitPath('principals/'.$row['email']);
            if ($rowPrefix !== $prefixPath) continue;

            $principal = array(
                'uri' => 'principals/'.$row['email'],
            	'email' => $row['emai'].'@login.vmfds.de',
            );
            foreach($this->fieldMap as $key=>$value) {
                if ($row[$value['dbField']]) {
                    $principal[$key] = $row[$value['dbField']];
                }
            }
            $principals[] = $principal;

        }

        return $principals;

    }

    /**
     * Returns a specific principal, specified by it's path.
     * The returned structure should be the exact same as from
     * getPrincipalsByPrefix.
     *
     * @param string $path
     * @return array
     */
    public function getPrincipalByPath($path) {

        $fields = array(
            'id',
            'email',
        );

        foreach($this->fieldMap as $key=>$value) {
            $fields[] = $value['dbField'];
        }

        // trick this into an uri in the form principals/<login> ...
        $uri = substr($path, 11);


        $stmt = $this->pdo->prepare('SELECT '.implode(',', $fields).'  FROM '. $this->tableName . ' WHERE email = ?');
        $stmt->execute(array($uri));

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return;

        $principal = array(
            'id'  => $row['id'],
            'uri' => 'principals/'.$row['email'],
            'email' => $row['email'].'@login.vmfds.de',
        );
        foreach($this->fieldMap as $key=>$value) {
            if ($row[$value['dbField']]) {
                $principal[$key] = $row[$value['dbField']];
            }
        }
        return $principal;

    }

    /**
     * Updates one ore more webdav properties on a principal.
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
     * @param string $path
     * @param DAV\PropPatch $propPatch
     */
    function updatePrincipal($path, DAV\PropPatch $propPatch) {

        $propPatch->handle(array_keys($this->fieldMap), function($properties) use ($path) {

            $query = "UPDATE " . $this->tableName . " SET ";
            $first = true;

            $values = [];

            foreach ($properties as $key => $value) {

                $dbField = $this->fieldMap[$key]['dbField'];

                if (!$first) {
                    $query .= ', ';
                }
                $first = false;
                $query .= $dbField . ' = :' . $dbField;
                $values[$dbField] = $value;

            }

            $query .= " WHERE uri = :uri";
            $values['uri'] = $path;

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($values);

            return true;

        });

    }

    /**
     * This method is used to search for principals matching a set of
     * properties.
     *
     * This search is specifically used by RFC3744's principal-property-search
     * REPORT.
     *
     * The actual search should be a unicode-non-case-sensitive search. The
     * keys in searchProperties are the WebDAV property names, while the values
     * are the property values to search on.
     *
     * By default, if multiple properties are submitted to this method, the
     * various properties should be combined with 'AND'. If $test is set to
     * 'anyof', it should be combined using 'OR'.
     *
     * This method should simply return an array with full principal uri's.
     *
     * If somebody attempted to search on a property the backend does not
     * support, you should simply return 0 results.
     *
     * You can also just return 0 results if you choose to not support
     * searching at all, but keep in mind that this may stop certain features
     * from working.
     *
     * @param string $prefixPath
     * @param array $searchProperties
     * @param string $test
     * @return array
     */
    function searchPrincipals($prefixPath, array $searchProperties, $test = 'allof') {
        if (count($searchProperties) == 0) return [];    //No criteria

        $query = 'SELECT email FROM ' . $this->tableName . ' WHERE ';
        $values = [];
        foreach ($searchProperties as $property => $value) {
            switch ($property) {
                case '{DAV:}displayname' :
                    $column = "email";
                    break;
                case '{http://sabredav.org/ns}email-address' :
                    $column = "email";
                    break;
                default :
                    // Unsupported property
                    return [];
            }
            if (count($values) > 0) $query .= (strcmp($test, "anyof") == 0 ? " OR " : " AND ");
            $query .= 'lower(' . $column . ') LIKE lower(?)';
            $values[] = '%' . $value . '%';

        }
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($values);

        $principals = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            // Checking if the principal is in the prefix
            list($rowPrefix) = URLUtil::splitPath($row['email']);
            if ($rowPrefix !== $prefixPath) continue;

            $principals[] = $row['email'];

        }

        return $principals;

    }

    /**
     * Returns the list of members for a group-principal
     *
     * @param string $principal
     * @return array
     */
    public function getGroupMemberSet($principal) {
    	return array();
    }

    /**
     * Returns the list of groups a principal is a member of
     *
     * @param string $principal
     * @return array
     */
    public function getGroupMembership($principal) {
    	return array();
    }

    /**
     * Updates the list of group members for a group principal.
     *
     * The principals should be passed as a list of uri's.
     *
     * @param string $principal
     * @param array $members
     * @return void
     */
    public function setGroupMemberSet($principal, array $members) {
    }

}
