<?php

/** @noinspection PhpParamsInspection */

namespace Migrator\Schema\Migrator;

class MergeModelFiles
{
    private $fullPath;
    private $model;
    private $messages = [];

    public function __construct($fullPath, ModelBuilder $model)
    {
        $this->fullPath = $fullPath;
        $this->model = $model;
    }

    public function merge()
    {
        $content = file_get_contents($this->fullPath);

        $content = $this->mergeMethods($content);
        $content = $this->mergeCasts($content);
        $content = $this->mergeGuarded($content);

        file_put_contents($this->fullPath, $content);
        //return new \RuntimeException("Cannot merge");
        return $content;
    }

    /**
     * @param $content
     *
     * @return null|string|string[]
     */
    private function mergeMethods($content)
    {
        foreach ($this->model->singleMethods() as $methodName => $def) {
            $def = trim($def);
            if (!preg_match("/function\s+$methodName\s*\(/", $content)) {
                $new = preg_replace('/\s*(\}\s*)$/', "\n    $def\n\n\\1", $content);
                if ($content == $new) {
                    // couldn't inject
                    dump("Cannot inject $methodName()");
                }
                $content = $new;
            }
        }

        return $content;
    }

    /**
     * @param $content
     *
     * @return null|string|string[]
     */
    private function mergeCasts($content)
    {
        if ($this->model->castFields()) {
            if (!str_contains($content, '$casts')) {
                $content = preg_replace(
                    '/(class\s+([\s\S]*?)\{)/s',
                    "\\1\n    ".trim($this->model->casts())."\n",
                    $content
                );
            } else {
                $regex = 'protected\s+(?P<casts>\$casts[\s\S]*?\]\s*;)';
                preg_match('#'.$regex.'#', $content, $m);

                try {
                    eval($m['casts']);
                    /** @var string $casts from eval */
                    $casts = array_merge($casts, $this->model->castFields());
                    $content = preg_replace('#'.$regex.'#', trim($this->model->casts($casts)), $content);
                } catch (\Exception $e) {
                }
            }
        }

        return $content;
    }

    /**
     * @param $content
     *
     * @return null|string|string[]
     */
    private function mergeGuarded($content)
    {
        if ($this->model->guardedFieldNames()) {
            if (!str_contains($content, '$guarded')) {
                $content = preg_replace(
                    '/(class\s+([\s\S]*?)\{)/s',
                    "\\1\n    ".trim($this->model->guarded())."\n",
                    $content
                );
            } else {
                $regex = 'protected\s+(?P<guarded>\$guarded.*?\]\s*;)';
                preg_match('#'.$regex.'#', $content, $m);

                try {
                    eval($m['guarded']);
                    /** @var string $guarded from eval */
                    $guarded = array_unique(array_merge($guarded, $this->model->guardedFieldNames()));
                    $content = preg_replace('#'.$regex.'#', trim($this->model->guarded($guarded)), $content);
                } catch (\Exception $e) {
                }
            }
        }

        return $content;
    }
}
