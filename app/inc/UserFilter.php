<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;


/**
 * Class UserFilter
 * @package app\inc
 */
class UserFilter
{
    /**
     * @var string
     */
    public $db;

    /**
     * @var string
     */
    public $userName;

    /**
     * @var string
     */
    public $service;

    /**
     * @var string
     */
    public $request;

    /**
     * @var string
     */
    public $ipAddress;

    /**
     * @var string
     */
    public $layer;

    /**
     * @var string
     */
    public $schema;

    public function __construct(string $db, string $userName, string $service, string $request, string $ipAddress, string $schema, string $layer)
    {
        $this->userName = $db;
        $this->userName = $userName;
        $this->service = $service;
        $this->service = $request;
        $this->ipAddress = $ipAddress;
        $this->schema = $schema;
        $this->layer = $layer;
    }

    public function getUserName(): string
    {
        return $this->userName;
    }
}