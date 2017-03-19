<?php

namespace Tulinkry\Photos\Presenters;

use Tulinkry\Application\UI\Presenter;
use Nette\Http\Request;
use Nette\Http\IResponse;
use Nette\Application\Responses;

use Tulinkry\Photos\Services\IVerifyService;
use Tulinkry\Photos\Services\IUserProvider;
use Tulinkry\Photos\Services\IAlbumProvider;
use Tulinkry\Photos\Services\IPhotosProvider;
use Tulinkry\Photos\Services\IContentProvider;


class DownloadController extends Presenter
{
	/**
	 * @var Request
	 * @inject
	 */
	public $httpRequest;

	/**
	 * @var IVerifyService
	 * @inject
	 */
	public $verifyService;

	/**
	 * @var IUserProvider
	 * @inject
	 */
	public $users;

	/**
	 * @var IAlbumProvider
	 * @inject
	 */
	public $albums;

	/**
	 * @var IPhotosProvider
	 * @inject
	 */
	public $photos;

	/**
	 * @var IContentProvider
	 * @inject
	 */
	public $content;

	public function sendJson($data, $code = IResponse::S200_OK) {
		$this->getHttpResponse()->setCode($code);
		parent::sendJson($data);
	}

	protected function authorize($operation, $id) {
		if (!$this->verifyService->verify($operation, $id, $this->httpRequest->getQuery('key'))) {
			$this->sendJson(array("error" => "Unauthorized."), IResponse::S401_UNAUTHORIZED);
		}
	}

	public function actionAlbums($userId)
	{
		$this->authorize(IVerifyService::ALBUMS_LISTING, $userId);
		if ( ( $albums = $this->users->find($userId) ) !== NULL ) {
			$this->sendJson($albums);
		}
		$this->sendJson(array("error" => "User does not exist."), IResponse::S404_NOT_FOUND);
	}

	public function actionPhotos($userId, $albumId)
	{
		$this->authorize(IVerifyService::PHOTOS_LISTING, $albumId);
		if ( $this->albums->find($albumId) === NULL ) {
			$this->sendJson(array("error" => "Album does not exist."), IResponse::S404_NOT_FOUND);
		}
		if ( ( $album = $this->albums->find($albumId, $this->httpRequest->getQuery()) ) !== NULL ) {
			$this->sendJson($album);
		}
		$this->sendJson(array("error" => "Invalid syntax."), IResponse::S400_BAD_REQUEST);	
	}

	public function actionPhoto($userId, $albumId, $photoId)
	{
		$this->authorize(IVerifyService::PHOTO_SINGLE, $photoId);
		if ( ( $photo = $this->photos->find($photoId, $this->httpRequest->getQuery()) ) ) {
			$this->sendJson($photo);
		}
		$this->sendJson(array("error" => "No photo found."), IResponse::S404_NOT_FOUND);
	}

	public function actionContent($photoId)
	{
		$this->authorize(IVerifyService::CONTENT_SINGLE, $photoId);
		if ( ( $photo = $this->content->find($photoId, $this->httpRequest->getQuery()) ) ) {
			// TODO: remove force download = false
			$this->sendResponse(new Responses\FileResponse($photo->path, $photo->name, $photo->contentType, false));
		}		
		$this->sendJson(array("error" => "No photo found."), IResponse::S404_NOT_FOUND);
	}
}