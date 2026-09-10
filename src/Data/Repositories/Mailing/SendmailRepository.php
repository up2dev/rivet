<?php
/**
 * SendmailRepository class file
 *
 * PHP Version 8.1
 *
 * @category Repository
 * @package  Rivet\Data\Repositories\Mailing
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Repositories\Mailing;

use Rivet\Data\Repositories\CRUD;

/**
 * SendmailRepository
 *
 * @category Repository
 * @package  Rivet\Data\Repositories\Mailing
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class SendmailRepository extends CRUD
{
    /**
     * The rows available as filters in the query
     *
     * @var array
     */
    protected $filters = [
        'id'         => 'id',
        'from'       => 'from',
        'to'         => 'to',
        'subject'    => 'subject',
        'sent_at'    => 'sent_at',
        'is_success' => 'is_success'
    ];

    /**
     * Modify an existing database item.
     *
     * @param array              $fields  The fields to register
     * @param \DateTimeImmutable $date    The creation date of the to retrieve
     *
     * @return bool
     */
    public function updateWhereToken(array $fields, string $token): bool
    {
        $this->model = $this->model_class::firstWhere('token', $token);

        return $this->register($fields);
    }

    /**
     * Call parent abstract register method.
     *
     * @inheritdoc
     * @see        parent::register()
     */
    protected function register(array $fields): bool
    {
        return $this->defaultRegister($fields);
    }
}
