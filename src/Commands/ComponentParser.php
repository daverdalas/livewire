<?php

namespace Livewire\Commands;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use function Livewire\str;

class ComponentParser
{
    protected $appPath;
    protected $viewPath;
    protected $component;
    protected $componentClass;
    protected $directories;

    public function __construct($classNamespace, $viewPath, $rawCommand)
    {

        $this->baseClassNamespace = $classNamespace;

        $classPath = static::generatePathFromNamespace($classNamespace);

        $this->baseClassPath = rtrim($classPath, DIRECTORY_SEPARATOR).'/';
        $this->baseViewPath = rtrim($viewPath, DIRECTORY_SEPARATOR).'/';

        $directories = preg_split('/[.\/(\\\\)]+/', $rawCommand);

        $camelCase = Str::camel(array_pop($directories));
        $kebabCase = Str::kebab($camelCase);

        $this->component = $kebabCase;
        $this->componentClass = Str::studly($this->component);

        $this->directories = array_map([Str::class, 'studly'], $directories);
    }

    public function component()
    {
        return $this->component;
    }

    public function classPath()
    {
        return $this->baseClassPath.collect()
            ->concat($this->directories)
            ->push($this->classFile())
            ->implode('/');
    }

    public function relativeClassPath() : string
    {
        return Str::replaceFirst(base_path().DIRECTORY_SEPARATOR, '', $this->classPath());
    }

    public function classFile()
    {
        return $this->componentClass.'.php';
    }

    public function classNamespace()
    {
        return empty($this->directories)
            ? $this->baseClassNamespace
            : $this->baseClassNamespace.'\\'.collect()
                ->concat($this->directories)
                ->map([Str::class, 'studly'])
                ->implode('\\');
    }

    public function className()
    {
        return $this->componentClass;
    }

    public function classContents($inline = false)
    {
        $stubName = $inline ? 'livewire.inline.stub' : 'livewire.stub';

        if(File::exists($stubPath = base_path('stubs/'.$stubName))) {
            $template = file_get_contents($stubPath);
        } else {
            $template = file_get_contents(__DIR__.DIRECTORY_SEPARATOR.$stubName);
        }

        if ($inline) {
            $template = preg_replace('/\[quote\]/', $this->wisdomOfTheTao(), $template);
        }

        return preg_replace_array(
            ['/\[namespace\]/', '/\[class\]/', '/\[view\]/'],
            [$this->classNamespace(), $this->className(), $this->viewName()],
            $template
        );
    }

    public function viewPath()
    {
        return $this->baseViewPath.collect()
            ->concat($this->directories)
            ->map([Str::class, 'kebab'])
            ->push($this->viewFile())
            ->implode(DIRECTORY_SEPARATOR);
    }

    public function relativeViewPath() : string
    {
        return Str::replaceFirst(base_path().'/', '', $this->viewPath());
    }

    public function viewFile()
    {
        return $this->component.'.blade.php';
    }

    public function viewName()
    {
        return collect()
            ->concat(explode('/',
                Str::after(resource_path('views'), $this->baseViewPath)))
            ->filter()
            ->concat($this->directories)
            ->map([Str::class, 'kebab'])
            ->push($this->component)
            ->implode('.');
    }

    public function viewContents()
    {
        if( ! File::exists($stubPath = base_path('stubs/livewire.view.stub'))) {
            $stubPath = __DIR__.DIRECTORY_SEPARATOR.'livewire.view.stub';
        }

        return preg_replace(
            '/\[quote\]/',
            $this->wisdomOfTheTao(),
            file_get_contents($stubPath)
        );
    }

    public function wisdomOfTheTao()
    {
        $wisdom = require __DIR__.DIRECTORY_SEPARATOR.'the-tao.php';

        return Arr::random($wisdom);
    }

    public static function generatePathFromNamespace($namespace)
    {
        $name = Str::replaceFirst(app()->getNamespace(), '', $namespace);

        return app('path').'/'.str_replace('\\', '/', $name);
    }
}
