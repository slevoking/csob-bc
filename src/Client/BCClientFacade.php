<?php declare(strict_types = 1);

namespace AsisTeam\CSOBBC\Client;

use AsisTeam\CSOBBC\Entity\IFile;
use AsisTeam\CSOBBC\Request\Filter;
use AsisTeam\CSOBBC\Response\GetDownloadFileListResponse;

final class BCClientFacade
{

	/** @var BCSoapClient */
	private $soapClient;

	/** @var BCHttpClient */
	private $httpClient;

	public function __construct(BCSoapClient $soapClient, BCHttpClient $httpClient)
	{
		$this->soapClient = $soapClient;
		$this->httpClient = $httpClient;
	}

	public function listFiles(?string $prevQueryDatetime = null, ?Filter $filter = null): GetDownloadFileListResponse
	{
		return $this->soapClient->getFiles($prevQueryDatetime, $filter);
	}

	public function download(string $url): string
	{
		return $this->httpClient->download($url);
	}

	/**
	 * @param IFile[] $files
	 */
	public function upload(array $files)
	{
		// register files for uploading
		$up = $this->soapClient->startUpload($files);

		$files = $up->getFiles();
		foreach ($files as $upFile) {
			// upload single file and receive it's uploaded id
			$this->httpClient->upload($upFile);
		}

		// confirm all files were uploaded and start processing them
		return $this->soapClient->finishUpload($files);
	}

}
