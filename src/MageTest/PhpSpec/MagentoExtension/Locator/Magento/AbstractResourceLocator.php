<?php
/**
 * MageSpec
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License, that is bundled with this
 * package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 *
 * http://opensource.org/licenses/MIT
 *
 * If you did not receive a copy of the license and are unable to obtain it
 * through the world-wide-web, please send an email
 * to <magetest@sessiondigital.com> so we can send you a copy immediately.
 *
 * @category   MageTest
 * @package    PhpSpec_MagentoExtension
 *
 * @copyright  Copyright (c) 2012-2013 MageTest team and contributors.
 */
namespace MageTest\PhpSpec\MagentoExtension\Locator\Magento;

use PhpSpec\Locator\Resource as ResourceInterface;
use PhpSpec\Locator\ResourceLocator as ResourceLocatorInterface;
use PhpSpec\Util\Filesystem;

abstract class AbstractResourceLocator
{
    /**
     * @var string
     */
    protected $srcPath;

    /**
     * @var string
     */
    protected $specPath;

    /**
     * @var string
     */
    protected $srcNamespace;

    /**
     * @var string
     */
    protected $specNamespace;

    /**
     * @var string
     */
    protected $fullSrcPath;

    /**
     * @var string
     */
    protected $fullSpecPath;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var string
     */
    protected $codePool;

    /**
     * @param string $srcNamespace
     * @param string $specNamespacePrefix
     * @param string $srcPath
     * @param string $specPath
     * @param Filesystem $filesystem
     * @param string $codePool
     */
    public function __construct(
        $srcNamespace = '',
        $specNamespacePrefix = '',
        $srcPath = 'src',
        $specPath = 'spec',
        Filesystem $filesystem = null,
        $codePool = 'local'
    ) {
        $this->filesystem = $filesystem ?: new Filesystem();
        $this->codePool = $codePool;

        $absoluteSrcPath = rtrim(realpath($srcPath), '/\\');
        $absoluteSpecPath = rtrim(realpath($specPath), '/\\');

        $this->srcPath = $absoluteSrcPath . DIRECTORY_SEPARATOR . $this->codePool . DIRECTORY_SEPARATOR;
        $this->specPath = $absoluteSpecPath . DIRECTORY_SEPARATOR . $this->codePool . DIRECTORY_SEPARATOR;
        $this->srcNamespace = ltrim(trim($srcNamespace, ' \\') . '\\', '\\');
        $this->specNamespace = trim($specNamespacePrefix, ' \\') . '\\';
        $this->fullSrcPath = $this->srcPath;
        $this->fullSpecPath = $this->specPath;

        $this->validatePaths($srcPath, $specPath);
    }

    /**
     * @return string
     */
    public function getFullSrcPath()
    {
        return $this->fullSrcPath;
    }

    /**
     * @return string
     */
    public function getFullSpecPath()
    {
        return $this->fullSpecPath;
    }

    /**
     * @return string
     */
    public function getSrcNamespace()
    {
        return $this->srcNamespace;
    }

    /**
     * @return string
     */
    public function getSpecNamespace()
    {
        return $this->specNamespace;
    }

    /**
     * @return string
     */
    public function getCodePool()
    {
        return $this->codePool;
    }

    /**
     * @return array
     */
    public function getAllResources()
    {
        return $this->findSpecResources($this->fullSpecPath);
    }

    /**
     * @param string $query
     * @return bool
     */
    public function supportsQuery($query)
    {
        return (bool) preg_match($this->getValidator(), $query) || $this->isSupported($query);
    }

    /**
     * @param string $query
     * @return array
     */
    public function findResources($query)
    {
        $path = $this->getCleanPath($query);

        foreach ([$this->fullSrcPath, $this->srcPath] as $srcPath) {
            if (0 === strpos($path, $srcPath)) {
                $path = $srcPath.substr($path, strlen($srcPath));
                $path = preg_replace('/\.php/', 'Spec.php', $path);

                return $this->findSpecResources($path);
            }
        }

        if (0 === strpos($path, $this->specPath)) {
            return $this->findSpecResources($path);
        }

        return [];
    }

    /**
     * @param string $classname
     * @return bool
     */
    public function supportsClass($classname)
    {
        $parts = explode('_', $classname);

        if (count($parts) < 2) {
            return false;
        }

        return (
            $this->supportsQuery($classname) ||
            $classname === implode('_', [$parts[0], $parts[1], $this->getClassType(), $parts[count($parts)-1]])
        );
    }

    /**
     * @param string $classname
     * @return ResourceInterface
     */
    public function createResource($classname)
    {
        preg_match($this->getValidator(), $classname, $matches);

        if (!empty($matches)) {
            array_shift($matches);
            array_shift($matches);

            $classname = $this->getClassnameFromMatches($matches);
        }

        return $this->getResource(explode('_', $classname), $this);
    }

    /**
     * @return int
     */
    abstract public function getPriority();

    /**
     * @param string $path
     * @return array
     */
    protected function findSpecResources($path)
    {
        if (!$this->filesystem->pathExists($path)) {
            return [];
        }

        if ('.php' === substr($path, -4)) {
            if (!$this->isSupported($path)) {
                return [];
            }

            return [$this->createResourceFromSpecFile(realpath($path))];
        }

        $resources = [];
        foreach ($this->filesystem->findSpecFilesIn($path) as $file) {
            $specFile = $file->getRealPath();
            if ($this->isSupported($specFile)) {
                $resources[] = $this->createResourceFromSpecFile($specFile);
            }
        }

        return $resources;
    }

    /**
     * @param string $path
     * @return ResourceInterface
     */
    private function createResourceFromSpecFile($path)
    {
        $relative = $this->getRelative($path);

        return $this->getResource(explode(DIRECTORY_SEPARATOR, $relative), $this);
    }

    /**
     * @param string $srcPath
     * @param string $specPath
     * @throws \InvalidArgumentException
     */
    private function validatePaths($srcPath, $specPath)
    {
        $invalidPath = DIRECTORY_SEPARATOR . $this->codePool . DIRECTORY_SEPARATOR;

        if ($invalidPath === $this->srcPath) {
            throw new \InvalidArgumentException(sprintf(
                'Source code path should be existing filesystem path, but "%s" given.',
                $srcPath
            ));
        }

        if ($invalidPath === $this->specPath) {
            throw new \InvalidArgumentException(sprintf(
                'Specs code path should be existing filesystem path, but "%s" given.',
                $specPath
            ));
        }
    }

    /**
     * @param string $query
     * @return string
     */
    private function getCleanPath($query)
    {
        $path = rtrim(realpath(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $query)), DIRECTORY_SEPARATOR);

        if ('.php' !== substr($path, -4)) {
            $path .= DIRECTORY_SEPARATOR;
        }

        return $path;
    }

    /**
     * @param array $matches
     * @return string
     */
    private function getClassnameFromMatches(array $matches)
    {
        $vendor = ucfirst(array_shift($matches));
        $module = ucfirst(array_shift($matches));

        return implode('_', [$vendor, $module, $this->getObjectName($matches)]);
    }

    /**
     * @param array $matches
     * @return string
     */
    protected function getObjectName(array $matches)
    {
        return $this->getClassType() . '_' . implode('_', array_map('ucfirst', explode('_', implode($matches))));
    }

    /**
     * @param string $path
     * @return string
     */
    protected function getRelative($path)
    {
        // cut "Spec.php" from the end
        $relative = substr($path, strlen($this->fullSpecPath), -4);
        return preg_replace('/Spec$/', '', $relative);
    }

    /**
     * @param string $file
     * @return bool
     */
    abstract protected function isSupported($file);

    /**
     * @param array $parts
     * @param ResourceLocatorInterface $locator
     * @return ResourceInterface
     */
    abstract protected function getResource(array $parts, ResourceLocatorInterface $locator);

    /**
     * @return string
     */
    abstract protected function getClassType();

    /**
     * @return string
     */
    abstract protected function getValidator();
}
