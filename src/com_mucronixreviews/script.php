<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Installer\InstallerScriptTrait;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

/**
 * The installer script service provider of the component: what happens to the reviews.
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
                     * The table with the reviews
                     *
                     * @var    string
                     * @since  0.1.0
                     */
                    private const REVIEWS = '#__mucronixreviews_reviews';

                    /**
                     * The table with the statistics. Its comment holds the version of the schema of kept tables.
                     *
                     * @var    string
                     * @since  0.1.0
                     */
                    private const STATS = '#__mucronixreviews_stats';

                    /**
                     * What the comment starts with
                     *
                     * @var    string
                     * @since  0.1.0
                     */
                    private const MARK = 'Mucronix Reviews schema ';

                    /**
                     * The first version of the schema: kept tables without a comment
                     *
                     * @var    string
                     * @since  0.1.0
                     */
                    private const FIRST_SCHEMA = '0.1.0';

                    /**
                     * Whether a new installation finds the tables of a former one
                     *
                     * @var    boolean
                     * @since  0.1.0
                     */
                    private bool $tablesKept = false;

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
                     * Looks for the tables of a former installation, before SQL creates new ones.
                     *
                     * @param   string            $type     The type of change
                     * @param   InstallerAdapter  $adapter  The adapter calling this method
                     *
                     * @return  boolean
                     *
                     * @since   0.1.0
                     */
                    public function preflight(string $type, InstallerAdapter $adapter): bool
                    {
                        if (!$this->checkRequirements($type, $adapter)) {
                            return false;
                        }

                        if ($type === 'install' || $type === 'discover_install') {
                            $this->tablesKept = \in_array($this->db->replacePrefix(self::REVIEWS), $this->db->getTableList(), true);
                        }

                        return true;
                    }

                    /**
                     * Brings kept tables to the schema of this version. On an installation Joomla writes down the latest
                     * schema and skips sql/updates, which is right for new tables only.
                     *
                     * @param   string            $type     The type of change
                     * @param   InstallerAdapter  $adapter  The adapter calling this method
                     *
                     * @return  boolean
                     *
                     * @since   0.1.0
                     */
                    public function postflight(string $type, InstallerAdapter $adapter): bool
                    {
                        if ($this->tablesKept) {
                            $app         = Factory::getApplication();
                            $extensionId = $this->extensionId();

                            $app->getLanguage()->load('com_mucronixreviews', JPATH_ADMINISTRATOR);

                            $this->setSchema($extensionId, $this->keptSchema());

                            if ($adapter->getParent()->parseSchemaUpdates($adapter->getManifest()->update->schemas, $extensionId) === false) {
                                $app->enqueueMessage(Text::_('COM_MUCRONIXREVIEWS_INSTALL_SCHEMA_FAILED'), 'error');
                            } else {
                                $app->enqueueMessage(Text::_('COM_MUCRONIXREVIEWS_INSTALL_DATA_FOUND'), 'info');
                            }
                        }

                        return $this->removeOutdatedFiles($type, $adapter);
                    }

                    /**
                     * Removes the tables only if the options ask for it. The options are still there at this moment,
                     * and options that were never saved ask to keep the tables.
                     *
                     * @param   InstallerAdapter  $adapter  The adapter calling this method
                     *
                     * @return  boolean
                     *
                     * @since   0.1.0
                     */
                    public function uninstall(InstallerAdapter $adapter): bool
                    {
                        $app = Factory::getApplication();

                        $app->getLanguage()->load('com_mucronixreviews', JPATH_ADMINISTRATOR);

                        if ((int) ($this->params()['keep_data'] ?? 1)) {
                            $this->markSchema();
                            $app->enqueueMessage(Text::_('COM_MUCRONIXREVIEWS_UNINSTALL_DATA_KEPT'), 'info');

                            return true;
                        }

                        foreach ([self::REVIEWS, self::STATS] as $table) {
                            $this->db->setQuery('DROP TABLE IF EXISTS ' . $this->db->quoteName($table))->execute();
                        }

                        $app->enqueueMessage(Text::_('COM_MUCRONIXREVIEWS_UNINSTALL_DATA_DELETED'), 'info');

                        return true;
                    }

                    /**
                     * Reads the options of the component.
                     *
                     * @return  array
                     *
                     * @since   0.1.0
                     */
                    private function params(): array
                    {
                        $query = $this->db->createQuery()
                            ->select($this->db->quoteName('params'))
                            ->from($this->db->quoteName('#__extensions'))
                            ->where($this->whereComponent());

                        return (array) json_decode((string) $this->db->setQuery($query)->loadResult(), true);
                    }

                    /**
                     * Reads the id of the component.
                     *
                     * @return  integer
                     *
                     * @since   0.1.0
                     */
                    private function extensionId(): int
                    {
                        $query = $this->db->createQuery()
                            ->select($this->db->quoteName('extension_id'))
                            ->from($this->db->quoteName('#__extensions'))
                            ->where($this->whereComponent());

                        return (int) $this->db->setQuery($query)->loadResult();
                    }

                    /**
                     * Writes the version of the schema into the comment of the table: the record Joomla keeps of it goes
                     * together with the component.
                     *
                     * @return  void
                     *
                     * @since   0.1.0
                     */
                    private function markSchema(): void
                    {
                        $extensionId = $this->extensionId();

                        $query = $this->db->createQuery()
                            ->select($this->db->quoteName('version_id'))
                            ->from($this->db->quoteName('#__schemas'))
                            ->where($this->db->quoteName('extension_id') . ' = :id')
                            ->bind(':id', $extensionId, ParameterType::INTEGER);

                        $version = (string) $this->db->setQuery($query)->loadResult() ?: self::FIRST_SCHEMA;

                        $this->db->setQuery(
                            'ALTER TABLE ' . $this->db->quoteName(self::STATS) . ' COMMENT = ' . $this->db->quote(self::MARK . $version)
                        )->execute();
                    }

                    /**
                     * Reads the version of the schema of kept tables.
                     *
                     * @return  string
                     *
                     * @since   0.1.0
                     */
                    private function keptSchema(): string
                    {
                        $table = $this->db->replacePrefix(self::STATS);

                        $query = $this->db->createQuery()
                            ->select($this->db->quoteName('TABLE_COMMENT'))
                            ->from($this->db->quoteName('information_schema.TABLES'))
                            ->where($this->db->quoteName('TABLE_SCHEMA') . ' = DATABASE()')
                            ->where($this->db->quoteName('TABLE_NAME') . ' = :name')
                            ->bind(':name', $table);

                        $comment = (string) $this->db->setQuery($query)->loadResult();

                        return preg_match('~^' . self::MARK . '(\d+(?:\.\d+)+)$~', $comment, $found) ? $found[1] : self::FIRST_SCHEMA;
                    }

                    /**
                     * Writes down the version of the schema where Joomla keeps it.
                     *
                     * @param   integer  $extensionId  The id of the component
                     * @param   string   $version      The version
                     *
                     * @return  void
                     *
                     * @since   0.1.0
                     */
                    private function setSchema(int $extensionId, string $version): void
                    {
                        $query = $this->db->createQuery()
                            ->update($this->db->quoteName('#__schemas'))
                            ->set($this->db->quoteName('version_id') . ' = :version')
                            ->where($this->db->quoteName('extension_id') . ' = :id')
                            ->bind(':version', $version)
                            ->bind(':id', $extensionId, ParameterType::INTEGER);

                        $this->db->setQuery($query)->execute();
                    }

                    /**
                     * The conditions that find the record of the component.
                     *
                     * @return  string[]
                     *
                     * @since   0.1.0
                     */
                    private function whereComponent(): array
                    {
                        return [
                            $this->db->quoteName('type') . ' = ' . $this->db->quote('component'),
                            $this->db->quoteName('element') . ' = ' . $this->db->quote('com_mucronixreviews'),
                        ];
                    }
                };
            }
        );
    }
};
