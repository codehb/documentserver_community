<?php declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Robin Appelman <robin@icewind.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\DocumentServer\Controller;

use OC\ForbiddenException;
use OCA\DocumentServer\OnlyOffice\URLDecoder;
use OCA\DocumentServer\XHRCommand\AuthCommand;
use OCA\DocumentServer\XHRCommand\IsSaveLock;
use OCA\DocumentServer\XHRCommand\SaveChangesCommand;
use OCA\DocumentServer\Document\DocumentStore;
use OCA\DocumentServer\Channel\ChannelFactory;
use OCP\AppFramework\Http\StreamResponse;
use OCP\IRequest;
use OCP\Security\ISecureRandom;
use function Sabre\HTTP\decodePathSegment;

class DocumentController extends SessionController {
	const INITIAL_RESPONSES = [
		'type' => 'license',
		'license' => [
			'type' => 3,
			'light' => false,
			'mode' => 0,
			'rights' => 1,
			'buildVersion' => '5.3.2',
			'buildNumber' => 20,
			'branding' => false,
			'customization' => false,
			'plugins' => false,
		],
	];

	const COMMAND_HANDLERS = [
		AuthCommand::class,
		IsSaveLock::class,
		SaveChangesCommand::class,
	];

	/** @var DocumentStore */
	private $documentStore;

	private $urlDecoder;

	public function __construct(
		$appName,
		IRequest $request,
		ChannelFactory $sessionFactory,
		DocumentStore $documentStore,
		ISecureRandom $random,
		URLDecoder $urlDecoder
	) {
		parent::__construct($appName, $request, $sessionFactory, $random);

		$this->documentStore = $documentStore;
		$this->urlDecoder = $urlDecoder;
	}


	protected function getInitialResponses(): array {
		return self::INITIAL_RESPONSES;
	}

	protected function getCommandHandlerClasses(): array {
		return self::COMMAND_HANDLERS;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function healthCheck() {
		return true;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function openDocument(int $docId, string $format, string $url) {
		// instead of downloading the source document from the onlyoffice app,
		// we get the source file from the token directly
		// this saves a round trip and gives us the fileid to use for saving later
		$url = decodePathSegment($url);
		$query = [];
		parse_str(parse_url($url, PHP_URL_QUERY), $query);

		$sourceFile = $this->urlDecoder->getFileForToken($query['doc']);
		if (!$sourceFile) {
			throw new ForbiddenException('Failed to get document');
		}

		$file = $this->documentStore->getDocumentForEditor($docId, $sourceFile, $format);

		return new StreamResponse($file->read());
	}
}