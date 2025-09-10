<?php

namespace Faravel\Http;

class ResponseFactory
{
    public function make(string $content = '', int $status = 200, array $headers = []): Response
    {
        $response = new Response();
        $response->setContent($content)->status($status);

        foreach ($headers as $key => $value) {
            $response->setHeader($key, $value);
        }

        return $response;
    }

    public function json(array $data, int $status = 200, array $headers = []): Response
    {
        $response = new Response();
        $response->setHeader('Content-Type', 'application/json');
        $response->setContent(json_encode($data));
        $response->status($status);

        foreach ($headers as $key => $value) {
            $response->setHeader($key, $value);
        }

        return $response;
    }

    public function view(string $viewName, array $data = []): Response
    {
        $html = \Faravel\Support\Facades\View::make($viewName, $data);
        return $this->make($html);
    }
}
