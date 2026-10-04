<?php

namespace Surface\Fonts\Console;

use Surface\Fonts\Support\AdafruitGfxHeader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Voyager\Console\GeneratorCommand;

#[AsCommand(name: 'make:font')]
class FontMakeCommand extends GeneratorCommand
{
    protected ?string $name = 'make:font';

    protected string $description = 'Create a GFXFont face under app/Fonts: an empty scaffold, or one imported from an Adafruit GFXfont .h';

    protected ?string $type = 'Font';

    protected function getStub(): string
    {
        return $this->resolveStubPath('/stubs/font.stub');
    }

    protected function resolveStubPath(string $stub): string
    {
        return file_exists($customPath = $this->venusian->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__.$stub;
    }

    protected function getDefaultNamespace(string $rootNamespace): string
    {
        return $rootNamespace.'\Fonts';
    }

    /** @return list<array{0: string, 1: string|null, 2: int, 3: string}> */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the font already exists'],
            ['from', null, InputOption::VALUE_REQUIRED, 'Path to an Adafruit GFXfont C header (.h) to import'],
        ];
    }

    public function handle(): ?bool
    {
        $from = $this->option('from');

        return is_string($from) && $from !== '' ? $this->handleFromHeader($from) : parent::handle();
    }

    protected function handleFromHeader(string $path): ?bool
    {
        if ($this->isReservedName($this->getNameInput())) {
            $this->components->error('The name "'.$this->getNameInput().'" is reserved by PHP.');

            return false;
        }

        $name = $this->qualifyClass($this->getNameInput());
        $target = $this->getPath($name);

        if ((! $this->hasOption('force') || ! $this->option('force')) && $this->alreadyExists($this->getNameInput())) {
            $this->components->error($this->type.' already exists.');

            return false;
        }

        $parsed = AdafruitGfxHeader::parseFile($path);
        $namespace = $this->getNamespace($name);
        $class = str_replace($namespace.'\\', '', $name);

        $this->makeDirectory($target);
        $this->files->put($target, $this->sortImports(AdafruitGfxHeader::renderClassSource($namespace, $class, $parsed)));

        if (windows_os()) {
            $target = str_replace('/', '\\', $target);
        }

        $this->components->info(sprintf('%s [%s] created successfully from %s.', $this->type, $target, $path));

        return null;
    }
}
