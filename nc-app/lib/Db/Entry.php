<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method ?int getId()
 * @method void setId(?int $id)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getEntryDate()
 * @method void setEntryDate(string $entryDate)
 * @method ?string getEntryTime()
 * @method void setEntryTime(?string $entryTime)
 * @method string getHeading()
 * @method void setHeading(string $heading)
 * @method string getBody()
 * @method void setBody(string $body)
 * @method ?int getRating()
 * @method void setRating(?int $rating)
 * @method string getTags()
 * @method void setTags(string $tags)
 * @method string getTrackers()
 * @method void setTrackers(string $trackers)
 * @method ?string getLocation()
 * @method void setLocation(?string $location)
 * @method string getAttachments()
 * @method void setAttachments(string $attachments)
 * @method ?string getCreatedAt()
 * @method void setCreatedAt(?string $createdAt)
 * @method ?string getModifiedAt()
 * @method void setModifiedAt(?string $modifiedAt)
 */
class Entry extends Entity implements \JsonSerializable {
	protected ?int $id = null;
	protected string $userId = '';
	protected string $entryDate = '';
	protected ?string $entryTime = null;
	protected string $heading = '';
	protected string $body = '';
	protected ?int $rating = null;
	protected string $tags = '[]';
	protected string $trackers = '[]';
	protected ?string $location = null;
	protected string $attachments = '[]';
	protected ?string $createdAt = null;
	protected ?string $modifiedAt = null;

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'date' => $this->entryDate,
			'time' => $this->entryTime,
			'heading' => $this->heading,
			'body' => $this->body,
			'rating' => $this->rating,
			'tags' => json_decode($this->tags, true) ?: [],
			'trackers' => json_decode($this->trackers, true) ?: [],
			'location' => $this->location !== null && $this->location !== '' ? json_decode($this->location, true) : null,
			'attachments' => json_decode($this->attachments, true) ?: [],
			'created' => $this->createdAt,
			'modified' => $this->modifiedAt,
		];
	}

	public static function fromArray(string $userId, array $data): self {
		$entry = new self();
		$entry->setId(isset($data['id']) ? (int) $data['id'] : null);
		$entry->setUserId($userId);
		$entry->setEntryDate((string) $data['date']);
		$entry->setEntryTime(isset($data['time']) && $data['time'] !== '' ? (string) $data['time'] : null);
		$entry->setHeading((string) ($data['heading'] ?? ''));
		$entry->setBody((string) ($data['body'] ?? ''));
		$rating = $data['rating'] ?? null;
		$entry->setRating($rating !== null ? (int) $rating : null);
		$entry->setTags((string) json_encode(array_values(array_map('strval', (array) ($data['tags'] ?? []))), JSON_UNESCAPED_UNICODE));
		$entry->setTrackers((string) json_encode((object) ($data['trackers'] ?? []), JSON_UNESCAPED_UNICODE));
		$location = $data['location'] ?? null;
		$entry->setLocation(is_array($location) ? (string) json_encode($location, JSON_UNESCAPED_UNICODE) : null);
		$entry->setAttachments((string) json_encode(array_values(array_map('strval', (array) ($data['attachments'] ?? []))), JSON_UNESCAPED_UNICODE));
		$entry->setCreatedAt(isset($data['created']) && $data['created'] !== '' ? (string) $data['created'] : null);
		$entry->setModifiedAt(isset($data['modified']) && $data['modified'] !== '' ? (string) $data['modified'] : null);
		return $entry;
	}
}
