<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method ?int getId()
 * @method void setId(?int $id)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getBody()
 * @method void setBody(string $body)
 * @method ?string getUpdatedAt()
 * @method void setUpdatedAt(?string $updatedAt)
 */
class Template extends Entity implements \JsonSerializable {
	protected string $userId = '';
	protected string $name = '';
	protected ?string $body = null;
	protected ?string $updatedAt = null;

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'name' => $this->name,
			'body' => $this->body ?? '',
			'updated_at' => $this->updatedAt,
		];
	}
}
