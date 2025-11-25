<?php
/**
 * Simple class fixture (UTF-8)
 */

namespace App\Models;

use App\Interfaces\UserInterface;
use App\Traits\Timestampable;

/**
 * User model class
 */
class User implements UserInterface
{
    use Timestampable;

    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $email;

    /**
     * Constructor
     *
     * @param string $name User name
     * @param string $email User email
     */
    public function __construct($name, $email)
    {
        $this->name = $name;
        $this->email = $email;
    }

    /**
     * Get user ID
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get user name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Unused private method
     */
    private function unusedMethod()
    {
        return 'unused';
    }
}

/**
 * Unused class
 */
class UnusedHelper
{
    public function help()
    {
        return 'help';
    }
}

/**
 * Unused function
 */
function unused_function()
{
    return 'unused';
}
