<?php

namespace Dilab;

use Cake\Filesystem\File;
use Cake\Filesystem\Folder;
use Dilab\Network\Request;
use Dilab\Network\Response;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Resumable
{
    public $debug = false;

    public $tempFolder = 'tmp';

    public $uploadFolder = 'test/files/uploads';

    // for testing
    public $deleteTmpFolder = true;

    protected $request;

    protected $response;

    protected $params;

    protected $chunkFile;

    protected $log;

    public function __construct(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;

        $this->log = new Logger('debug');
        $this->log->pushHandler(new StreamHandler('debug.log', Logger::DEBUG));
    }

    public function process()
    {
        if (!empty($this->resumableParams())) {
            if (!empty($this->request->file())) {
                echo "process file";

                return $this->handleChunk();
            } else {
                return $this->handleTestChunk();
            }
        }
    }

    public function handleTestChunk()
    {
        $identifier = $this->resumableParam('identifier');
        $filename = $this->resumableParam('filename');
        $chunkNumber = $this->resumableParam('chunkNumber');

        if ($chunkNumber == 1) {
            $tmpChunkDir = $this->tmpChunkDir($identifier);
            if (!file_exists($tmpChunkDir)) {
                mkdir($tmpChunkDir);
            }
        }

        if (!$this->isChunkUploaded($identifier, $filename, $chunkNumber)) {
            return $this->response->header(204);
        } else {
            return $this->response->header(200);
        }
    }

    public function handleChunk()
    {
        $file = $this->request->file();
        $identifier = $this->resumableParam('identifier');
        $filename = $this->resumableParam('filename');
        $chunkNumber = $this->resumableParam('chunkNumber');
        $chunkSize = $this->resumableParam('chunkSize');
        $totalSize = $this->resumableParam('totalSize');

        if (!$this->isChunkUploaded($identifier, $filename, $chunkNumber)) {
            $chunkFile = $this->tmpChunkDir($identifier) . DIRECTORY_SEPARATOR . $this->tmpChunkFilename($filename, $chunkNumber);
            $this->moveUploadedFile($file['tmp_name'], $chunkFile);
        }

        if ($this->isFileUploadComplete($filename, $identifier, $chunkSize, $totalSize)) {
            $this->createFileAndDeleteTmp($identifier, $filename);

            return $this->response->header(201);
        }

        return $this->response->header(200);
    }

    private function createFileAndDeleteTmp($identifier, $filename)
    {
        $tmpFolder = new Folder($this->tmpChunkDir($identifier));
        $chunkFiles = $tmpFolder->read(true, true, true)[1];
        if ($this->createFileFromChunks($chunkFiles, $this->uploadFolder . DIRECTORY_SEPARATOR . $filename) && $this->deleteTmpFolder) {
            $tmpFolder->delete();
        }
    }

    private function resumableParam($shortName)
    {
        $resumableParams = $this->resumableParams();
        //identifier
        if (!isset($resumableParams[$shortName])) {
            return null;
        }

        return $resumableParams[$shortName];
    }

    public function resumableParams()
    {
        return array_merge($_GET, $_POST);
    }

    public function isFileUploadComplete($filename, $identifier, $chunkSize, $totalSize)
    {
        if ($_POST["chunkNumber"] != $_POST["totalChunks"]) {
            return false;
        }

        if ($chunkSize <= 0) {
            return false;
        }

        for ($i = 1; $i < $_POST["totalChunks"]; $i++) {
            if (!$this->isChunkUploaded($identifier, $filename, $i)) {
                return false;
            }
        }

        return true;
    }

    public function isChunkUploaded($identifier, $filename, $chunkNumber)
    {
        $file = new File($this->tmpChunkDir($identifier) . DIRECTORY_SEPARATOR . $this->tmpChunkFilename($filename, $chunkNumber));

        return $file->exists();
    }

    public function tmpChunkDir($identifier)
    {
        $tmpChunkDir = $this->tempFolder . DIRECTORY_SEPARATOR . $identifier;

        return $tmpChunkDir;
    }

    public function tmpChunkFilename($filename, $chunkNumber)
    {
        return $filename . '.part' . $chunkNumber;
    }

    public function createFileFromChunks($chunkFiles, $destFile)
    {
        $this->log('Beginning of create files from chunks');

        natsort($chunkFiles);

        print_r($chunkFiles);

        $destFile = new File($destFile, true);

        $index = 1;
        foreach ($chunkFiles as $chunkFile) {
            $file = new File($chunkFile);
            $destFile->append($file->read());
            echo "append " . $index;
            $index++;

            $this->log('Append ', ['chunk file' => $chunkFile]);
        }

        $this->log('End of create files from chunks');

        return $destFile->exists();
    }

    public function moveUploadedFile($file, $destFile)
    {
        $file = new File($file);
        if ($file->exists()) {
            return $file->copy($destFile);
        }

        return false;
    }

    public function setRequest($request)
    {
        $this->request = $request;
    }

    public function setResponse($response)
    {
        $this->response = $response;
    }

    private function log($msg, $ctx = [])
    {
        if ($this->debug) {
            $this->log->addDebug($msg, $ctx);
        }
    }
}
