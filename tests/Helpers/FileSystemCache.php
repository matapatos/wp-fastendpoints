<?php

declare(strict_types=1);

namespace Wp\FastEndpoints\Tests\Helpers;

/**
 * Class used to emulate a file system.
 *
 * @since 0.9.0
 */
class FileSystemCache
{
    /**
     * Root caching directory
     *
     * @since 0.9.0
     */
    private string $dir;

    public function __construct()
    {
        $name = uniqid('tests-');
        $this->dir = sys_get_temp_dir().\DIRECTORY_SEPARATOR.$name;

        if (! file_exists($this->dir)) {
            mkdir($this->dir, 0700, true);
        }
    }

    /**
     * Retrieves the fullpath of a given file/directory regarding the caching directory
     *
     * @since 0.9.0
     *
     * @param  string  $filename  - The filename in question.
     */
    public function getFullpath(string $filename): string
    {
        return $this->dir.\DIRECTORY_SEPARATOR.$filename;
    }

    /**
     * Creates a multiple directories inside the root caching directory.
     *
     * @since 0.9.0
     *
     * @uses touchDirectory
     *
     * @param  array<string>  $directories  - An array of directories to be created.
     * @return array<string>
     */
    public function touchDirectories(array $directories): array
    {
        $fullpathDirectories = [];
        foreach ($directories as $dir) {
            $fullpathDirectories[] = $this->touchDirectory($dir);
        }

        return $fullpathDirectories;
    }

    /**
     * Creates a recusive directory inside the root caching directory.
     *
     * @since 0.9.0
     *
     * @param  string  $directoryName  - The name of the directory to be created.
     */
    public function touchDirectory(string $directoryName): string
    {
        $fullpath = $this->getFullpath($directoryName);
        if (file_exists($fullpath)) {
            return $fullpath;
        }

        mkdir($fullpath, 0700, true);

        return $fullpath;
    }

    /**
     * Saves data to a file inside the caching directory
     *
     * @since 0.9.0
     *
     * @param  string  $filename  - The filename of the file to be stored.
     * @param  mixed  $data  - Serializable data to be put into the file.
     */
    public function store(string $filename, $data)
    {
        $fileDirectory = dirname($filename);
        if ($fileDirectory != '/') {
            $this->touchDirectory($fileDirectory);
        }

        $fullpath = $this->getFullpath($filename);
        file_put_contents($fullpath, serialize($data));

        return $fullpath;
    }

    /**
     * Retrieves the caching root directory
     *
     * @since 0.9.0
     */
    public function getRootDir(): string
    {
        return $this->dir;
    }
}
