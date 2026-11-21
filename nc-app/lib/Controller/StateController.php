<?php
declare(strict_types=1);

namespace OCA\Tagebuch\Controller;

use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IRequest;

class StateController extends OCSController {
	public function __construct(string $appName, IRequest $request, private IDBConnection $db, private string $userId) {
		parent::__construct($appName, $request);
	}

	private function matches(IQueryBuilder $q, string $key): IQueryBuilder {
		return $q->where($q->expr()->eq('user_id', $q->createNamedParameter($this->userId)))
			->andWhere($q->expr()->eq('state_key', $q->createNamedParameter($key)));
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/state/{key}')]
	public function get(string $key): DataResponse {
		$q = $this->db->getQueryBuilder();
		$q->select('revision', 'payload')->from('tagebuch_state');
		$result = $this->matches($q, $key)->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return new DataResponse($row ?: ['revision' => '', 'payload' => '']);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/state/{key}')]
	public function put(string $key, string $revision, string $payload): DataResponse {
		$isVault = $key === 'vault';
		if (!($isVault || preg_match('/^draft-\d{4}-\d{2}-\d{2}-(?:new|\d+)$/D', $key))
			|| ($isVault && ($revision !== '' || $payload === ''))) {
			return new DataResponse([], 400);
		}
		$q = $this->db->getQueryBuilder();
		if ($payload !== '') {
			$decoded = json_decode($payload, true);
			if (!is_array($decoded)) return new DataResponse([], 400);
			if ($isVault) {
				if (!is_string($decoded['salt'] ?? null) || strlen((string)base64_decode($decoded['salt'], true)) !== 16
					|| !is_string($decoded['verifier'] ?? null) || !str_contains($decoded['verifier'], '.')) {
					return new DataResponse([], 400);
				}
			} elseif (!is_string($decoded['cipher'] ?? null)) {
				if (!is_string($decoded['value'] ?? null)) return new DataResponse([], 400);
				$check = $this->db->getQueryBuilder();
				$check->select('revision')->from('tagebuch_state');
				if ($this->matches($check, 'vault')->executeQuery()->fetchOne() !== false) return new DataResponse([], 409);
			}
		}
		$next = bin2hex(random_bytes(16));
		if ($revision === '') {
			$q->insert('tagebuch_state')->values([
				'user_id' => $q->createNamedParameter($this->userId),
				'state_key' => $q->createNamedParameter($key),
				'payload' => $q->createNamedParameter($payload),
				'revision' => $q->createNamedParameter($next),
			]);
			try {
				$q->executeStatement();
			} catch (Exception $e) {
				if ($e->getReason() === Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) return new DataResponse([], 409);
				throw $e;
			}
		} else {
			if ($payload === '') {
				$q->delete('tagebuch_state');
				$next = '';
			} else {
				$q->update('tagebuch_state')->set('payload', $q->createNamedParameter($payload))
					->set('revision', $q->createNamedParameter($next));
			}
			$this->matches($q, $key)->andWhere($q->expr()->eq('revision', $q->createNamedParameter($revision)));
			if ($q->executeStatement() !== 1) return new DataResponse([], 409);
		}
		return new DataResponse(['revision' => $next]);
	}
}
