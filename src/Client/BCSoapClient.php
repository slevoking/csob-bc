<?php declare(strict_types = 1);

namespace AsisTeam\CSOBBC\Client;

use AsisTeam\CSOBBC\Entity\IFile;
use AsisTeam\CSOBBC\Exception\Logical\InvalidArgumentException;
use AsisTeam\CSOBBC\Exception\LogicalException;
use AsisTeam\CSOBBC\Exception\Runtime\RequestException;
use AsisTeam\CSOBBC\Request\Filter;
use AsisTeam\CSOBBC\Response\FinishUploadFileListResponse;
use AsisTeam\CSOBBC\Response\GetDownloadFileListResponse;
use AsisTeam\CSOBBC\Response\StartUploadFileListResponse;
use SoapClient;
use SoapFault;

class BCSoapClient
{

	public const API_DATE_FORMAT = DATE_W3C;

	/** @var SoapClient */
	private $client;

	/** @var string */
	private $contractNo;

	/** @var string */
	private $clientAppGuid;

	public function __construct(SoapClient $client, string $contractNo, string $clientAppGuid)
	{
		$this->client        = $client;
		$this->contractNo    = $contractNo;
		$this->clientAppGuid = $clientAppGuid;
	}

	public function getFiles(
		?string $prevQueryTimestamp = null,
		?Filter $filter = null
	): GetDownloadFileListResponse
	{
		try {
			$opts = $this->prepareGetFilesOpts($prevQueryTimestamp, $filter);
			$resp = $this->client->GetDownloadFileList_v2($opts);

			return GetDownloadFileListResponse::fromResponse($resp);
		} catch (SoapFault $e) {
			$this->throwSoapError($e);
		}
	}

	/**
	 * @param IFile[] $files
	 */
	public function startUpload(array $files): StartUploadFileListResponse
	{
		try {
			$opts = $this->prepareStartUploadOpts($files);
			$resp = $this->client->StartUploadFileList_v3($opts);

			var_dump($resp);

			return StartUploadFileListResponse::fromResponse($resp, $files);
		} catch (SoapFault $e) {
			$this->throwSoapError($e);
		}
	}

	/**
	 * @param IFile[] $files
	 */
	public function finishUpload(array $files): FinishUploadFileListResponse
	{
		try {
			$opts = $this->prepareFinishUploadOpts($files);
			$resp = $this->client->FinishUploadFileList_v2($opts);

			return FinishUploadFileListResponse::fromResponse($resp);
		} catch (SoapFault $e) {
			$this->throwSoapError($e);
		}
	}

	/**
	 * @return mixed[]
	 */
	private function prepareGetFilesOpts(?string $prevQueryTimestamp = null, ?Filter $filter = null): array
	{
		// defaults options
		$opts = [
			'ContractNumber' => $this->contractNo,
			'Filter'         => [
				'ClientAppGuid' => $this->clientAppGuid,
			],
		];

		if ($prevQueryTimestamp !== null) {
			$opts['PrevQueryTimestamp'] = $prevQueryTimestamp;
		}

		if ($filter !== null) {
			// file types filter
			if (is_array($filter->getFileTypes())) {
				$opts['Filter']['FileTypes'] = [];
				foreach ($filter->getFileTypes() as $fType) {
					$opts['Filter']['FileTypes'][] = $fType;
				}
			}

			// file name filter
			if ($filter->getFileName() !== null) {
				$opts['Filter']['FileName'] = $filter->getFileName();
			}

			// file creation dates
			if ($filter->getCreatedBefore() !== null) {
				$opts['Filter']['CreatedBefore'] = $filter->getCreatedBefore()->format(self::API_DATE_FORMAT);
			}
			if ($filter->getCreatedAfter() !== null) {
				$opts['Filter']['CreatedAfter'] = $filter->getCreatedAfter()->format(self::API_DATE_FORMAT);
			}

			// override ClientAppGuid if given in filter
			if ($filter->getClientAppGuid() !== null) {
				$opts['Filter']['ClientAppGuid'] = $filter->getClientAppGuid();
			}
		}

		return $opts;
	}

	/**
	 * @param IFile[] $files
	 * @return mixed[]
	 */
	private function prepareStartUploadOpts(array $files): array
	{
		$opts = [
			'ContractNumber' => $this->contractNo,
			'ClientAppGuid'  => $this->clientAppGuid,
			'FileList'       => ['ImportFileDetail' => []],
		];

		if (count($files) === 0) {
			throw new InvalidArgumentException('Want to upload files, but no files given.');
		}

		foreach ($files as $f) {
			$opts['FileList']['ImportFileDetail'][] = $this->fileToArray($f);
		}

		return $opts;
	}

	/**
	 * @return mixed[]
	 */
	private function fileToArray(IFile $f): array
	{
		$a = [
			'Filename' => $f->getFileName(),
			'Hash'     => $f->getHash(),
			'Size'     => $f->getSize(),
			'Format'   => $f->getFormat(),
			'Mode'     => $f->getUploadMode(),
			'SkipCheckDuplicates' => true,
		];

		if ($f->getSeparator() !== null) {
			$a['Separator'] = $f->getSeparator();
		}

		return $a;
	}

	/**
	 * @param IFile[] $uploadedFiles
	 * @return mixed[]
	 */
	private function prepareFinishUploadOpts(array $uploadedFiles): array
	{
		$opts = [
			'ContractNumber' => $this->contractNo,
			'ClientAppGuid'  => $this->clientAppGuid,
			'FileList'       => ['FileId' => []],
		];

		foreach ($uploadedFiles as $upFile) {
			if ($upFile->getUpload() === null) {
				throw new LogicalException(sprintf(
					'Cannot run finish upload. File "%s" does not contain uploaded file id. Was it really uploaded?',
					$upFile->getFileName()
				));
			}

			$opts['FileList']['FileId'][] = [
				'Filename'  => $upFile->getFileName(),
				'Hash'      => $upFile->getHash(),
				'NewFileId' => $upFile->getUpload()->getFileId(),
			];
		}

		return $opts;
	}

	private function throwSoapError(SoapFault $e): void
	{
		// could be $this->client->__getLastRequest() for inspecting what we sent
		throw new RequestException(
			sprintf('SOAP Fault %s. Request: %s', $e->getMessage(), $this->client->__getLastResponse()),
			0,
			$e
		);
	}

}
