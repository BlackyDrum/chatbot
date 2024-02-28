<?php

namespace App\Http\Controllers;

use App\Models\Collections;
use App\Models\Files;
use Codewithkyrian\ChromaDB\ChromaDB;
use Codewithkyrian\ChromaDB\Embeddings\JinaEmbeddingFunction;
use GuzzleHttp\Client;
use Smalot\PdfParser\Parser;

class ChromaController extends Controller
{
    public static function createPromptWithContext($collectionName, $message, $pastMessages = null)
    {
        $collection = self::getCollection($collectionName);

        $queryResponse = $collection->query(
            queryTexts: [
                $message
            ],
            nResults: config('chromadb.max_document_results')
        );

        $enhancedMessage = "Try to answer the following user question. Always try to answer in the language from the user's question.\n" .
                           "Below you will find some context that may help. Ignore it if it seems irrelevant.\n" .
                           "Below you will also find the user messages from the past. Always take that into account too.\n\n";

        $index = 1;
        foreach ($queryResponse->ids[0] as $id) {
            $file = Files::query()
                ->where('embedding_id', '=', $id)
                ->first();

            $enhancedMessage .= "----------\n";
            $enhancedMessage .= "Context Document $index:\n" . $file->content . "\n";
            $enhancedMessage .= "----------\n";
            $index++;
        }

        $index = 1;
        if ($pastMessages) {
            foreach ($pastMessages as $pastMessage) {
                $enhancedMessage .= "----------\n";
                $enhancedMessage .= "Recent User Message $index:\n" . $pastMessage->user_message . "\n";
                $enhancedMessage .= "----------\n";
                $index++;
            }
        }

        $enhancedMessage .= "\nCurrent User Message:\n" . $message;

        return $enhancedMessage;
    }

    public static function createEmbedding($model)
    {
        $model->embedding_id = substr($model->path, strrpos($model->path, '/') + 1);

        $pathToFile = storage_path() . '/app/' . $model->path;

        $filename = $model->name;

        if (str_ends_with($filename, 'pdf')) {
            $parser = new Parser();

            try {
                $pdf = $parser->parseFile($pathToFile);
            } catch (\Exception $exception) {

                if (file_exists($pathToFile)) {
                    unlink($pathToFile);
                }

                return [
                    'status' => false,
                    'message' => $exception->getMessage(),
                ];
            }

            $text = $pdf->getText();
        }
        else {
            $text = file_get_contents($pathToFile);
        }

        $model->content = $text;

        try {
            $collection = Collections::query()->find($model->collection_id)->name;

            $collection = self::getCollection($collection);

            $id = [$model->embedding_id];
            $document = [$text];
            $metadata = [
                [
                    'filename' => $model->name,
                    'size' => $model->size,
                ]
            ];

            $collection->add(
                ids: $id,
                metadatas: $metadata,
                documents: $document
            );

        } catch (\Exception $exception) {
            if (file_exists($pathToFile)) {
                unlink($pathToFile);
            }

            return [
                'status' => false,
                'message' => $exception->getMessage(),
            ];
        }

        $model->save();

        return [
            'status' => true,
        ];
    }

    public static function deleteEmbedding($model)
    {
        $collection = Collections::query()->find($model->collection_id)->name;

        try {
            $collection = self::getCollection($collection);

            $collection->delete([$model->embedding_id]);

            $pathToFile = storage_path() . '/app/' . $model->path;

            if (file_exists($pathToFile)) {
                unlink($pathToFile);
            }
        } catch (\Exception $exception) {
            return [
                'status' => false,
                'message' => $exception->getMessage(),
            ];
        }

        return [
            'status' => true,
        ];
    }

    public static function createCollection($name)
    {
        try {
            $chromaDB = self::getClient();

            $embeddingFunction = self::getEmbeddingFunction();

            $chromaDB->createCollection($name, embeddingFunction: $embeddingFunction);
        } catch (\Exception $exception) {
            return [
                'status' => false,
                'message' => $exception->getMessage(),
            ];
        }

        return [
            'status' => true,
        ];
    }

    public static function deleteCollection($model)
    {
        $files = Files::query()->where('collection_id', '=', $model->id)->get();

        try {
            $chromaDB = self::getClient();

            $chromaDB->deleteCollection($model->name);
        } catch (\Exception $exception) {
            return [
                'status' => false,
                'message' => $exception->getMessage(),
            ];
        }

        foreach ($files as $file) {
            $pathToFile = storage_path() . '/app/' . $file->path;

            if (file_exists($pathToFile)) {
                unlink($pathToFile);
            }
        }

        return [
            'status' => true,
        ];
    }

    public static function getCollection($collection)
    {
        $chromaDB = self::getClient();

        $embeddingFunction = self::getEmbeddingFunction();

        return $chromaDB->getCollection($collection, embeddingFunction: $embeddingFunction);
    }

    public static function getEmbeddingFunction()
    {
        return new JinaEmbeddingFunction(config('chromadb.jina_api_key'), 'jina-embeddings-v2-base-de');
    }

    public static function getClient()
    {
        return ChromaDB::factory()
            ->withHost(config('chromadb.chroma_host'))
            ->withPort(config('chromadb.chroma_port'))
            ->withDatabase(config('chromadb.chroma_database'))
            ->withTenant(config('chromadb.chroma_tenant'))
            ->withHttpClient(new Client([
                'base_uri' => config('chromadb.chroma_host') . ':' . config('chromadb.chroma_port'),
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . config('chromadb.chroma_server_auth_credentials')
                ],
            ]))
            ->connect();
    }
}
