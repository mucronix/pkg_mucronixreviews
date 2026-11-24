<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  pkg_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Installer\InstallerScriptTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

/**
 * The installer script service provider of the package.
 *
 * @since  0.1.0
 */
return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function register(Container $container): void
    {
        $container->set(
            InstallerScriptInterface::class,
            function (Container $container) {
                return new class ($container->get(DatabaseInterface::class)) implements InstallerScriptInterface {
                    use InstallerScriptTrait {
                        preflight as private checkRequirements;
                        postflight as private removeOutdatedFiles;
                    }

                    /**
                     * The groups of the plugins of the package
                     *
                     * @var    string[]
                     * @since  0.3.0
                     */
                    private const GROUPS = ['system', 'content'];

                    /**
                     * The groups of the plugins that were there before
                     *
                     * @var    string[]
                     * @since  0.3.0
                     */
                    private array $present = [];

                    /**
                     * Constructor
                     *
                     * @param   DatabaseInterface  $db  The database driver
                     *
                     * @since   0.1.0
                     */
                    public function __construct(private DatabaseInterface $db)
                    {
                        $this->minimumPhp    = '8.3.0';
                        $this->minimumJoomla = '6.0.0';
                    }

                    /**
                     * Looks which of the plugins are there already.
                     *
                     * @param   string            $type     The type of change
                     * @param   InstallerAdapter  $adapter  The adapter calling this method
                     *
                     * @return  boolean
                     *
                     * @since   0.3.0
                     */
                    public function preflight(string $type, InstallerAdapter $adapter): bool
                    {
                        // What is discovered has its record before it is installed
                        if ($type === 'install' || $type === 'update') {
                            $query = $this->db->createQuery()
                                ->select($this->db->quoteName('folder'))
                                ->from($this->db->quoteName('#__extensions'))
                                ->where($this->wherePlugins());

                            $this->present = $this->db->setQuery($query)->loadColumn();
                        }

                        return $this->checkRequirements($type, $adapter);
                    }

                    /**
                     * Enables the plugins that were not there before, about the others a site owner has decided. Without
                     * them, the deletion of a user or of an article goes unnoticed.
                     *
                     * @param   string            $type     The type of change
                     * @param   InstallerAdapter  $adapter  The adapter calling this method
                     *
                     * @return  boolean  True on success
                     *
                     * @since   0.1.0
                     */
                    public function postflight(string $type, InstallerAdapter $adapter): bool
                    {
                        $new = array_diff(self::GROUPS, $this->present);

                        if ($type !== 'uninstall' && $new) {
                            $query = $this->db->createQuery()
                                ->update($this->db->quoteName('#__extensions'))
                                ->set($this->db->quoteName('enabled') . ' = 1')
                                ->where($this->wherePlugins())
                                ->whereIn($this->db->quoteName('folder'), array_values($new), ParameterType::STRING);

                            $this->db->setQuery($query)->execute();
                        }

                        return $this->removeOutdatedFiles($type, $adapter);
                    }

                    /**
                     * The conditions that find the plugins.
                     *
                     * @return  string[]
                     *
                     * @since   0.3.0
                     */
                    private function wherePlugins(): array
                    {
                        return [
                            $this->db->quoteName('type') . ' = ' . $this->db->quote('plugin'),
                            $this->db->quoteName('element') . ' = ' . $this->db->quote('mucronixreviews'),
                        ];
                    }
                };
            }
        );
    }
};
