<?php
/**
 * Dependency Analyzer
 *
 * Detects unused Composer dependencies
 */

namespace PhpKnip\Analyzer;

use PhpKnip\Composer\ComposerJsonParser;
use PhpKnip\Composer\ComposerLockParser;
use PhpKnip\Composer\AutoloadResolver;
use PhpKnip\Resolver\Reference;

/**
 * Analyzes for unused Composer dependencies
 */
class DependencyAnalyzer implements AnalyzerInterface
{
    /**
     * @var ComposerJsonParser|null
     */
    private $composerJson;

    /**
     * @var ComposerLockParser|null
     */
    private $composerLock;

    /**
     * @var AutoloadResolver|null
     */
    private $autoloadResolver;

    /**
     * Well-known packages that provide non-class functionality
     * These need special handling as they may not have direct class usage
     */
    private static $specialPackages = array(
        'php' => true,
        'ext-*' => true,
        'lib-*' => true,
    );

    /**
     * Packages that are typically used via configuration rather than code
     */
    private static $configurationPackages = array(
        'roave/security-advisories',
        'composer/installers',
        'symfony/flex',
        'dealerdirect/phpcodesniffer-composer-installer',
    );

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'dependency-analyzer';
    }

    /**
     * Set Composer JSON parser
     *
     * @param ComposerJsonParser $parser Parser
     *
     * @return $this
     */
    public function setComposerJson(ComposerJsonParser $parser)
    {
        $this->composerJson = $parser;
        return $this;
    }

    /**
     * Set Composer lock parser
     *
     * @param ComposerLockParser $parser Parser
     *
     * @return $this
     */
    public function setComposerLock(ComposerLockParser $parser)
    {
        $this->composerLock = $parser;
        return $this;
    }

    /**
     * Set autoload resolver
     *
     * @param AutoloadResolver $resolver Resolver
     *
     * @return $this
     */
    public function setAutoloadResolver(AutoloadResolver $resolver)
    {
        $this->autoloadResolver = $resolver;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function analyze(AnalysisContext $context)
    {
        $issues = array();

        if ($this->composerJson === null) {
            return $issues;
        }

        // Build autoload resolver if not set
        if ($this->autoloadResolver === null && $this->composerLock !== null) {
            $this->autoloadResolver = new AutoloadResolver();
            $this->autoloadResolver->build($this->composerJson, $this->composerLock);
        }

        // Get all declared dependencies (excluding PHP and extensions)
        $declaredDeps = $this->composerJson->getPackageDependencies(true);

        // Find used packages from references
        $usedPackages = $this->findUsedPackages($context->getReferences());

        // Check each declared dependency
        foreach ($declaredDeps as $packageName => $versionConstraint) {
            // Skip special packages
            if ($this->isSpecialPackage($packageName)) {
                continue;
            }

            // Skip configuration-only packages
            if ($this->isConfigurationPackage($packageName)) {
                continue;
            }

            // Skip if ignored
            if ($this->shouldIgnore($packageName, $context)) {
                continue;
            }

            // Check if package is used
            if (!isset($usedPackages[$packageName])) {
                $isDev = $this->composerJson->hasRequireDev($packageName);
                $issues[] = $this->createIssue($packageName, $isDev);
            }
        }

        return $issues;
    }

    /**
     * Find packages that are used based on references
     *
     * @param array $references References
     *
     * @return array<string, bool> Package names
     */
    private function findUsedPackages(array $references)
    {
        $usedPackages = array();

        if ($this->autoloadResolver === null) {
            return $usedPackages;
        }

        // Reference types that indicate package usage
        $relevantTypes = array(
            Reference::TYPE_NEW,
            Reference::TYPE_EXTENDS,
            Reference::TYPE_IMPLEMENTS,
            Reference::TYPE_USE_TRAIT,
            Reference::TYPE_USE_IMPORT,
            Reference::TYPE_STATIC_CALL,
            Reference::TYPE_TYPE_HINT,
            Reference::TYPE_RETURN_TYPE,
            Reference::TYPE_INSTANCEOF,
            Reference::TYPE_CATCH,
            Reference::TYPE_CLASS_STRING,
            Reference::TYPE_FUNCTION_CALL,
        );

        foreach ($references as $ref) {
            if (!in_array($ref->getType(), $relevantTypes, true)) {
                continue;
            }

            $symbolName = $ref->getSymbolName();
            if ($symbolName === '(dynamic)') {
                continue;
            }

            // Try to resolve to a package
            $package = null;

            if ($ref->getType() === Reference::TYPE_FUNCTION_CALL) {
                $package = $this->autoloadResolver->resolveFunction($symbolName);
            } else {
                $package = $this->autoloadResolver->resolveClass($symbolName);
            }

            if ($package !== null) {
                $usedPackages[$package] = true;
            }

            // Also check parent for static calls
            $parent = $ref->getSymbolParent();
            if ($parent !== null && $parent !== '(dynamic)') {
                $package = $this->autoloadResolver->resolveClass($parent);
                if ($package !== null) {
                    $usedPackages[$package] = true;
                }
            }
        }

        return $usedPackages;
    }

    /**
     * Check if package is a special package (PHP, extensions, etc.)
     *
     * @param string $packageName Package name
     *
     * @return bool
     */
    private function isSpecialPackage($packageName)
    {
        if ($packageName === 'php') {
            return true;
        }

        if (strpos($packageName, 'ext-') === 0) {
            return true;
        }

        if (strpos($packageName, 'lib-') === 0) {
            return true;
        }

        return false;
    }

    /**
     * Check if package is a configuration-only package
     *
     * @param string $packageName Package name
     *
     * @return bool
     */
    private function isConfigurationPackage($packageName)
    {
        return in_array($packageName, self::$configurationPackages, true);
    }

    /**
     * Check if package should be ignored
     *
     * @param string $packageName Package name
     * @param AnalysisContext $context Analysis context
     *
     * @return bool
     */
    private function shouldIgnore($packageName, AnalysisContext $context)
    {
        $ignorePatterns = $context->getConfigValue('ignore', array());
        $dependencyPatterns = isset($ignorePatterns['dependencies'])
            ? $ignorePatterns['dependencies']
            : array();

        foreach ($dependencyPatterns as $pattern) {
            if ($this->matchesPattern($packageName, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match pattern with wildcard support
     *
     * @param string $name Name to check
     * @param string $pattern Pattern
     *
     * @return bool
     */
    private function matchesPattern($name, $pattern)
    {
        $regex = '/^' . str_replace(
            array('\\*', '\\?'),
            array('.*', '.'),
            preg_quote($pattern, '/')
        ) . '$/'
        ;

        return preg_match($regex, $name) === 1;
    }

    /**
     * Create an unused dependency issue
     *
     * @param string $packageName Package name
     * @param bool $isDev Whether this is a dev dependency
     *
     * @return Issue
     */
    private function createIssue($packageName, $isDev)
    {
        $type = $isDev ? 'dev ' : '';
        $message = sprintf("Package '%s' is declared as %sdependency but never used", $packageName, $type);

        $issue = new Issue(
            Issue::TYPE_UNUSED_DEPENDENCY,
            $message,
            $isDev ? Issue::SEVERITY_INFO : Issue::SEVERITY_WARNING
        );

        $issue->setSymbolName($packageName);
        $issue->setSymbolType('dependency');
        $issue->setMetadata('isDev', $isDev);

        // Set file path to composer.json
        if ($this->composerJson !== null && $this->composerJson->getBaseDir() !== null) {
            $issue->setFilePath($this->composerJson->getBaseDir() . '/composer.json');
        }

        return $issue;
    }

    /**
     * Get dependency usage report
     *
     * @param AnalysisContext $context Analysis context
     *
     * @return array Report data
     */
    public function getDependencyReport(AnalysisContext $context)
    {
        $report = array(
            'declared' => array(),
            'used' => array(),
            'unused' => array(),
            'missing' => array(),
        );

        if ($this->composerJson === null) {
            return $report;
        }

        // Build resolver if needed
        if ($this->autoloadResolver === null && $this->composerLock !== null) {
            $this->autoloadResolver = new AutoloadResolver();
            $this->autoloadResolver->build($this->composerJson, $this->composerLock);
        }

        // Declared dependencies
        $report['declared'] = $this->composerJson->getPackageDependencies(true);

        // Used packages
        $report['used'] = array_keys($this->findUsedPackages($context->getReferences()));

        // Unused packages
        foreach ($report['declared'] as $package => $version) {
            if (!$this->isSpecialPackage($package) &&
                !$this->isConfigurationPackage($package) &&
                !in_array($package, $report['used'], true)) {
                $report['unused'][] = $package;
            }
        }

        // Missing packages (used but not declared)
        foreach ($report['used'] as $package) {
            if (!isset($report['declared'][$package]) &&
                $package !== $this->composerJson->getName() &&
                $package !== '(project)') {
                $report['missing'][] = $package;
            }
        }

        return $report;
    }
}
