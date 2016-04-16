<?php
//----------------------------------------------------------------------------------------------------------------------
/**
 * PhpStratum
 *
 * @copyright 2005-2015 Paul Water / Set Based IT Consultancy (https://www.setbased.nl)
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @link
 */
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Stratum\MySql;

use phpDocumentor\Reflection\DocBlock;
use SetBased\Exception\FallenException;
use SetBased\Exception\RuntimeException;
use SetBased\Stratum\MySql\StaticDataLayer as DataLayer;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Class for loading a single stored routine into a MySQL instance from pseudo SQL file.
 */
class RoutineLoaderHelper
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The default character set under which the stored routine will be loaded and run.
   *
   * @var string
   */
  private $myCharacterSet;

  /**
   * The default collate under which the stored routine will be loaded and run.
   *
   * @var string
   */
  private $myCollate;

  /**
   * The key or index columns (depending on the designation type) of the stored routine .
   *
   * @var string[]
   */
  private $myColumns;

  /**
   * The column types of columns of the table for bulk insert of the stored routine.
   *
   * @var string[]
   */
  private $myColumnsTypes;

  /**
   * The designation type of the stored routine.
   *
   * @var string
   */
  private $myDesignationType;

  /**
   * All DocBlock parts as found in the source of the stored routine.
   *
   * @var array
   */
  private $myDocBlockPartsSource = [];

  /**
   * The DocBlock parts to be used by the wrapper generator.
   *
   * @var array
   */
  private $myDocBlockPartsWrapper;

  /**
   * Information about parameters with specific format (string in CSV format etc.) pass to the stored routine.
   *
   * @var array
   */
  private $myExtendedParameters;

  /**
   * The keys in the PHP array for bulk insert.
   *
   * @var string[]
   */
  private $myFields;

  /**
   * The last modification time of the source file.
   *
   * @var int
   */
  private $myMTime;

  /**
   * The information about the parameters of the stored routine.
   *
   * @var array[]
   */
  private $myParameters = [];

  /**
   * The metadata of the stored routine. Note: this data is stored in the metadata file and is generated by PhpStratum.
   *
   * @var array
   */
  private $myPhpStratumMetadata;

  /**
   * The old metadata of the stored routine.  Note: this data comes from the metadata file.
   *
   * @var array
   */
  private $myPhpStratumOldMetadata;

  /**
   * The placeholders in the source file.
   *
   * @var array
   */
  private $myPlaceholders;

  /**
   * The old metadata of the stored routine. Note: this data comes from information_schema.ROUTINES.
   *
   * @var array
   */
  private $myRdbmsOldRoutineMetadata;

  /**
   * The replace pairs (i.e. placeholders and their actual values, see strst).
   *
   * @var array
   */
  private $myReplace = [];

  /**
   * A map from placeholders to their actual values.
   *
   * @var array
   */
  private $myReplacePairs = [];

  /**
   * The name of the stored routine.
   *
   * @var string
   */
  private $myRoutineName;

  /**
   * The source code as a single string of the stored routine.
   *
   * @var string
   */
  private $myRoutineSourceCode;

  /**
   * The source code as an array of lines string of the stored routine.
   *
   * @var array
   */
  private $myRoutineSourceCodeLines;

  /**
   * The stored routine type (i.e. procedure or function) of the stored routine.
   *
   * @var string
   */
  private $myRoutineType;

  /**
   * The extension of the source file of the stored routine.
   *
   * @var string
   */
  private $mySourceFileExtension;

  /**
   * The source filename holding the stored routine.
   *
   * @var string
   */
  private $mySourceFilename;

  /**
   * The SQL mode under which the stored routine will be loaded and run.
   *
   * @var string
   */
  private $mySqlMode;

  /**
   * If designation type is bulk_insert the table name for bulk insert.
   *
   * @var string
   */
  private $myTableName;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
   *
   * @param string $theRoutineFilename         The filename of the source of the stored routine.
   * @param string $theRoutineFileExtension    The extension of the source file of the stored routine.
   * @param array  $thePhpStratumMetadata      The metadata of the stored routine from PhpStratum.
   * @param array  $theReplacePairs            A map from placeholders to their actual values.
   * @param array  $theRdbmsOldRoutineMetadata The old metadata of the stored routine from MySQL.
   * @param string $theSqlMode                 The SQL mode under which the stored routine will be loaded and run.
   * @param string $theCharacterSet            The default character set under which the stored routine will be loaded
   *                                           and run.
   * @param string $theCollate                 The key or index columns (depending on the designation type) of the
   *                                           stored routine.
   */
  public function __construct($theRoutineFilename,
                              $theRoutineFileExtension,
                              $thePhpStratumMetadata,
                              $theReplacePairs,
                              $theRdbmsOldRoutineMetadata,
                              $theSqlMode,
                              $theCharacterSet,
                              $theCollate
  )
  {
    $this->mySourceFilename          = $theRoutineFilename;
    $this->mySourceFileExtension     = $theRoutineFileExtension;
    $this->myPhpStratumMetadata      = $thePhpStratumMetadata;
    $this->myReplacePairs            = $theReplacePairs;
    $this->myRdbmsOldRoutineMetadata = $theRdbmsOldRoutineMetadata;
    $this->mySqlMode                 = $theSqlMode;
    $this->myCharacterSet            = $theCharacterSet;
    $this->myCollate                 = $theCollate;
  }

//--------------------------------------------------------------------------------------------------------------------
  /**
   * Loads the stored routine into the instance of MySQL.
   *
   * @return array|false If the stored routine is loaded successfully the new mata data of the stored routine. Otherwise
   *                     false.
   */
  public function loadStoredRoutine()
  {
    try
    {
      // We assume that the basename of the routine file and routine name are equal.
      $this->myRoutineName = basename($this->mySourceFilename, $this->mySourceFileExtension);

      // Save old metadata.
      $this->myPhpStratumOldMetadata = $this->myPhpStratumMetadata;

      // Get modification time of the source file.
      $this->myMTime = filemtime($this->mySourceFilename);

      // Load the stored routine into MySQL only if the source has changed or the value of a placeholder.
      $load = $this->getMustReload();
      if ($load)
      {
        // Read the stored routine source code.
        $this->myRoutineSourceCode = file_get_contents($this->mySourceFilename);

        // Split the stored routine source code into lines.
        $this->myRoutineSourceCodeLines = explode("\n", $this->myRoutineSourceCode);
        if ($this->myRoutineSourceCodeLines===false) return false;

        // Extract placeholders from the stored routine source code.
        $ok = $this->getPlaceholders();
        if ($ok===false) return false;

        // Extract the designation type and key or index columns from the stored routine source code.
        $ok = $this->getDesignationType();
        if ($ok===false) return false;

        // Extract the stored routine type (procedure or function) and stored routine name from the source code.
        $ok = $this->getName();
        if ($ok===false) return false;

        // Load the stored routine into MySQL.
        $this->loadRoutineFile();

        // If the stored routine is a bulk insert stored procedure, enhance metadata with table columns information.
        if ($this->myDesignationType=='bulk_insert')
        {
          $this->getBulkInsertTableColumnsInfo();
        }

        // Get info about parameters with specific layout like cvs string etc. form the stored routine.
        $this->getExtendedParametersInfo();

        // Get the parameters types of the stored routine from metadata of MySQL.
        $this->getRoutineParametersInfo();

        // Compose the DocBlock parts to be used by the wrapper generator.
        $this->getDocBlockPartsWrapper();

        // Validate the parameters found the DocBlock in the source of the stored routine against the parameters from
        // the metadata of MySQL.
        $this->validateParameterLists();

        // Update Metadata of the stored routine.
        $this->updateMetadata();
      }

      return $this->myPhpStratumMetadata;
    }
    catch (\Exception $e)
    {
      echo $e->getMessage(), "\n";

      return false;
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Converts MySQL data type to the PHP data type.
   *
   * @param string[] $theParameterInfo
   *
   * @return string
   * @throws \Exception
   */
  private function columnTypeToPhpType($theParameterInfo)
  {
    switch ($theParameterInfo['data_type'])
    {
      case 'tinyint':
      case 'smallint':
      case 'mediumint':
      case 'int':
      case 'bigint':

      case 'year':

      case 'bit':
        $php_type = 'int';
        break;

      case 'decimal':
        $php_type = ($theParameterInfo['numeric_scale']=='0') ? 'int' : 'float';
        break;

      case 'float':
      case 'double':
        $php_type = 'float';
        break;

      case 'varbinary':
      case 'binary':

      case 'char':
      case 'varchar':

      case 'time':
      case 'timestamp':

      case 'date':
      case 'datetime':

      case 'enum':
      case 'set':

      case 'tinytext':
      case 'text':
      case 'mediumtext':
      case 'longtext':

      case 'tinyblob':
      case 'blob':
      case 'mediumblob':
      case 'longblob':
        $php_type = 'string';
        break;

      case 'list_of_int':
        $php_type = 'string|int[]';
        break;

      default:
        throw new FallenException('column type', $theParameterInfo['data_type']);
    }

    return $php_type;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Drops the stored routine if it exists.
   */
  private function dropRoutine()
  {
    if (isset($this->myRdbmsOldRoutineMetadata))
    {
      $sql = sprintf('drop %s if exists %s',
                     $this->myRdbmsOldRoutineMetadata['routine_type'],
                     $this->myRoutineName);

      DataLayer::executeNone($sql);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   *  Gets the column names and column types of the current table for bulk insert.
   */
  private function getBulkInsertTableColumnsInfo()
  {
    // Check if table is a temporary table or a non-temporary table.
    $query                  = sprintf('
select 1
from   information_schema.TABLES
where table_schema = database()
and   table_name   = %s', DataLayer::quoteString($this->myTableName));
    $table_is_non_temporary = DataLayer::executeSingleton0($query);

    // Create temporary table if table is non-temporary table.
    if (!$table_is_non_temporary)
    {
      $query = 'call '.$this->myRoutineName.'()';
      DataLayer::executeNone($query);
    }

    // Get information about the columns of the table.
    $query   = sprintf('describe `%s`', $this->myTableName);
    $columns = DataLayer::executeRows($query);

    // Drop temporary table if table is non-temporary.
    if (!$table_is_non_temporary)
    {
      $query = sprintf('drop temporary table `%s`', $this->myTableName);
      DataLayer::executeNone($query);
    }

    // Check number of columns in the table match the number of fields given in the designation type.
    $n1 = count($this->myColumns);
    $n2 = count($columns);
    if ($n1!=$n2)
    {
      throw new RuntimeException("Number of fields %d and number of columns %d don't match.", $n1, $n2);
    }

    // Fill arrays with column names and column types.
    $tmp_column_types = [];
    $tmp_fields       = [];
    foreach ($columns as $column)
    {
      preg_match('(\\w+)', $column['Type'], $type);
      $tmp_column_types[] = $type['0'];
      $tmp_fields[]       = $column['Field'];
    }

    $this->myColumnsTypes = $tmp_column_types;
    $this->myFields       = $tmp_fields;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Extracts the designation type of the stored routine.
   *
   * @return bool True on success. Otherwise returns false.
   */
  private function getDesignationType()
  {
    $ret = true;
    $key = array_search('begin', $this->myRoutineSourceCodeLines);

    if ($key!==false)
    {
      for ($i = 1; $i<$key; $i++)
      {
        $n = preg_match('/^\s*--\s+type:\s*(\w+)\s*(.+)?\s*$/',
                        $this->myRoutineSourceCodeLines[$key - $i],
                        $matches);
        if ($n==1)
        {
          $this->myDesignationType = $matches[1];
          switch ($this->myDesignationType)
          {
            case 'bulk_insert':
              $m = preg_match('/^([a-zA-Z0-9_]+)\s+([a-zA-Z0-9_,]+)$/',
                              $matches[2],
                              $info);
              if ($m==0)
              {
                throw new RuntimeException("Error: Expected: -- type: bulk_insert <table_name> <columns> in file '%s'.",
                                           $this->mySourceFilename);
              }
              $this->myTableName = $info[1];
              $this->myColumns   = explode(',', $info[2]);
              break;

            case 'rows_with_key':
            case 'rows_with_index':
              $this->myColumns = explode(',', $matches[2]);
              break;

            default:
              if (isset($matches[2])) $ret = false;
          }
          break;
        }
        if ($i==$key - 1) $ret = false;
      }
    }
    else
    {
      $ret = false;
    }

    if ($ret===false)
    {
      echo sprintf("Error: Unable to find the designation type of the stored routine in file '%s'.\n",
                   $this->mySourceFilename);
    }

    return $ret;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   *  Extracts the DocBlock (in parts) from the source of the stored routine.
   */
  private function getDocBlockPartsSource()
  {
    // Get the DocBlock for the source.
    $tmp = '';
    foreach ($this->myRoutineSourceCodeLines as $line)
    {
      $n = preg_match('/create\\s+(procedure|function)\\s+([a-zA-Z0-9_]+)/i', $line);
      if ($n) break;
      else $tmp .= $line."\n";
    }

    $phpdoc = new DocBlock($tmp);

    // Get the short description.
    $this->myDocBlockPartsSource['sort_description'] = $phpdoc->getShortDescription();

    // Get the long description.
    $this->myDocBlockPartsSource['long_description'] = $phpdoc->getLongDescription()->getContents();

    // Get the description for each parameter of the stored routine.
    foreach ($phpdoc->getTags() as $key => $tag)
    {
      if ($tag->getName()=='param')
      {
        $content     = $tag->getContent();
        $description = $tag->getDescription();

        // Gets name of parameter from routine doc block.
        $name = trim(substr($content, 0, strlen($content) - strlen($description)));

        $tmp   = [];
        $lines = explode("\n", $description);
        foreach ($lines as $line)
        {
          $tmp[] = trim($line);
        }
        $description = implode("\n", $tmp);

        $this->myDocBlockPartsSource['parameters'][$key] = ['name'        => $name,
                                                            'description' => $description];
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   *  Generates the DocBlock parts to be used by the wrapper generator.
   */
  private function getDocBlockPartsWrapper()
  {
    // Get the DocBlock parts from the source of the stored routine.
    $this->getDocBlockPartsSource();

    // Generate the parameters parts of the DocBlock to be used by the wrapper.
    $parameters = [];
    foreach ($this->myParameters as $parameter_info)
    {
      $parameters[] = ['name'                 => $parameter_info['name'],
                       'php_type'             => $this->columnTypeToPhpType($parameter_info),
                       'data_type_descriptor' => $parameter_info['data_type_descriptor'],
                       'description'          => $this->getParameterDocDescription($parameter_info['name'])];
    }

    // Compose all the DocBlock parts to be used by the wrapper generator.
    $this->myDocBlockPartsWrapper = ['sort_description' => $this->myDocBlockPartsSource['sort_description'],
                                     'long_description' => $this->myDocBlockPartsSource['long_description'],
                                     'parameters'       => $parameters];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Gets info of extended parameters.
   *
   * @throws \Exception
   */
  private function getExtendedParametersInfo()
  {
    $key = array_search('begin', $this->myRoutineSourceCodeLines);

    if ($key!==false)
    {
      for ($i = 1; $i<$key; $i++)
      {
        $k = preg_match('/^\s*--\s+param:(?:\s*(\w+)\s+(\w+)(?:(?:\s+([^\s-])\s+([^\s-])\s+([^\s-])\s*$)|(?:\s*$)))?/',
                        $this->myRoutineSourceCodeLines[$key - $i + 1],
                        $matches);

        if ($k==1)
        {
          $count = count($matches);
          if ($count==3 || $count==6)
          {
            $parameter_name = $matches[1];
            $data_type      = $matches[2];

            if ($count==6)
            {
              $list_delimiter = $matches[3];
              $list_enclosure = $matches[4];
              $list_escape    = $matches[5];
            }
            else
            {
              $list_delimiter = ',';
              $list_enclosure = '"';
              $list_escape    = '\\';
            }

            if (!isset($this->myExtendedParameters[$parameter_name]))
            {
              $this->myExtendedParameters[$parameter_name] = ['name'      => $parameter_name,
                                                              'data_type' => $data_type,
                                                              'delimiter' => $list_delimiter,
                                                              'enclosure' => $list_enclosure,
                                                              'escape'    => $list_escape];
            }
            else
            {
              throw new RuntimeException("Duplicate parameter '%s' in file '%s'.",
                                         $parameter_name,
                                         $this->mySourceFilename);
            }
          }
          else
          {
            throw new RuntimeException("Error: Expected: -- param: <field_name> <type_of_list> [delimiter enclosure escape] in file '%s'.",
                                       $this->mySourceFilename);
          }
        }
      }
    }
  }


  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns true if the source file must be load or reloaded. Otherwise returns false.
   *
   * @return bool
   */
  private function getMustReload()
  {
    // If this is the first time we see the source file it must be loaded.
    if (!isset($this->myPhpStratumOldMetadata)) return true;

    // If the source file has changed the source file must be loaded.
    if ($this->myPhpStratumOldMetadata['timestamp']!=$this->myMTime) return true;

    // If the value of a placeholder has changed the source file must be loaded.
    foreach ($this->myPhpStratumOldMetadata['replace'] as $place_holder => $old_value)
    {
      if (!isset($this->myReplacePairs[strtoupper($place_holder)]) ||
        $this->myReplacePairs[strtoupper($place_holder)]!==$old_value
      )
      {
        return true;
      }
    }

    // If stored routine not exists in database the source file must be loaded.
    if (!isset($this->myRdbmsOldRoutineMetadata)) return true;

    // If current sql-mode is different the source file must reload.
    if ($this->myRdbmsOldRoutineMetadata['sql_mode']!=$this->mySqlMode) return true;

    // If current character is different the source file must reload.
    if ($this->myRdbmsOldRoutineMetadata['character_set_client']!=$this->myCharacterSet) return true;

    // If current collation is different the source file must reload.
    if ($this->myRdbmsOldRoutineMetadata['collation_connection']!=$this->myCollate) return true;

    return false;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Extracts the name of the stored routine and the stored routine type (i.e. procedure or function) source.
   *
   * @todo Skip comments and string literals.
   * @return bool Returns true on success, false otherwise.
   */
  private function getName()
  {
    $ret = true;

    $n = preg_match('/create\\s+(procedure|function)\\s+([a-zA-Z0-9_]+)/i', $this->myRoutineSourceCode, $matches);
    if ($n==1)
    {
      $this->myRoutineType = strtolower($matches[1]);

      if ($this->myRoutineName!=$matches[2])
      {
        echo sprintf("Error: Stored routine name '%s' does not match filename in file '%s'.\n",
                     $matches[2],
                     $this->mySourceFilename);
        $ret = false;
      }
    }
    else
    {
      $ret = false;
    }

    if (!isset($this->myRoutineType))
    {
      echo sprintf("Error: Unable to find the stored routine name and type in file '%s'.\n",
                   $this->mySourceFilename);
    }

    return $ret;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Gets description by name of the parameter as found in the DocBlock of the stored routine.
   *
   * @param string $theName Name of the parameter.
   *
   * @return string
   */
  private function getParameterDocDescription($theName)
  {
    if (isset($this->myDocBlockPartsSource['parameters']))
    {
      foreach ($this->myDocBlockPartsSource['parameters'] as $parameter_doc_info)
      {
        if ($parameter_doc_info['name']===$theName) return $parameter_doc_info['description'];
      }
    }

    return null;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Extracts the placeholders from the stored routine source.
   *
   * @return bool True if all placeholders are defined, false otherwise.
   */
  private function getPlaceholders()
  {
    preg_match_all('(@[A-Za-z0-9\_\.]+(\%type)?@)', $this->myRoutineSourceCode, $matches);

    $ret                  = true;
    $this->myPlaceholders = [];

    if (!empty($matches[0]))
    {
      foreach ($matches[0] as $placeholder)
      {
        if (!isset($this->myReplacePairs[strtoupper($placeholder)]))
        {
          echo sprintf("Error: Unknown placeholder '%s' in file '%s'.\n", $placeholder, $this->mySourceFilename);
          $ret = false;
        }

        if (!isset($this->myPlaceholders[$placeholder]))
        {
          $this->myPlaceholders[$placeholder] = $placeholder;
        }
      }
    }

    if ($ret===true)
    {
      foreach ($this->myPlaceholders as $placeholder)
      {
        $this->myReplace[$placeholder] = $this->myReplacePairs[strtoupper($placeholder)];
      }
      ksort($this->myReplace);
    }

    return $ret;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Gets info about the parameters of the stored routine.
   */
  private function getRoutineParametersInfo()
  {
    $query = sprintf("
select t2.parameter_name
,      t2.data_type
,      t2.numeric_precision
,      t2.numeric_scale
,      t2.character_set_name
,      t2.collation_name
,      t2.dtd_identifier
from            information_schema.ROUTINES   t1
left outer join information_schema.PARAMETERS t2  on  t2.specific_schema = t1.routine_schema and
                                                      t2.specific_name   = t1.routine_name and
                                                      t2.parameter_mode   is not null
where t1.routine_schema = database()
and   t1.routine_name   = '%s'", $this->myRoutineName);

    $routine_parameters = DataLayer::executeRows($query);

    foreach ($routine_parameters as $key => $routine_parameter)
    {
      if ($routine_parameter['parameter_name'])
      {
        $data_type_descriptor = $routine_parameter['dtd_identifier'];
        if (isset($routine_parameter['character_set_name']))
        {
          $data_type_descriptor .= ' character set '.$routine_parameter['character_set_name'];
        }
        if (isset($routine_parameter['collation_name']))
        {
          $data_type_descriptor .= ' collation '.$routine_parameter['collation_name'];
        }

        $routine_parameter['name']                 = $routine_parameter['parameter_name'];
        $routine_parameter['data_type_descriptor'] = $data_type_descriptor;

        $this->myParameters[$key] = $routine_parameter;
      }
    }

    $this->updateParametersInfo();
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Loads the stored routine into the database.
   */
  private function loadRoutineFile()
  {
    echo sprintf("Loading %s %s\n",
                 $this->myRoutineType,
                 $this->myRoutineName);

    // Set magic constants specific for this stored routine.
    $this->setMagicConstants();

    // Replace all place holders with their values.
    $lines          = explode("\n", $this->myRoutineSourceCode);
    $routine_source = [];
    foreach ($lines as $i => &$line)
    {
      $this->myReplace['__LINE__'] = $i + 1;
      $routine_source[$i]          = strtr($line, $this->myReplace);
    }
    $routine_source = implode("\n", $routine_source);

    // Unset magic constants specific for this stored routine.
    $this->unsetMagicConstants();

    // Drop the stored procedure or function if its exists.
    $this->dropRoutine();

    // Set the SQL-mode under which the stored routine will run.
    $sql = sprintf("set sql_mode ='%s'", $this->mySqlMode);
    DataLayer::executeNone($sql);

    // Set the default character set and collate under which the store routine will run.
    $sql = sprintf("set names '%s' collate '%s'", $this->myCharacterSet, $this->myCollate);
    DataLayer::executeNone($sql);

    // Finally, execute the SQL code for loading the stored routine.
    DataLayer::executeNone($routine_source);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Adds magic constants to replace list.
   */
  private function setMagicConstants()
  {
    $real_path = realpath($this->mySourceFilename);

    $this->myReplace['__FILE__']    = "'".DataLayer::realEscapeString($real_path)."'";
    $this->myReplace['__ROUTINE__'] = "'".$this->myRoutineName."'";
    $this->myReplace['__DIR__']     = "'".DataLayer::realEscapeString(dirname($real_path))."'";
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Removes magic constants from current replace list.
   */
  private function unsetMagicConstants()
  {
    unset($this->myReplace['__FILE__']);
    unset($this->myReplace['__ROUTINE__']);
    unset($this->myReplace['__DIR__']);
    unset($this->myReplace['__LINE__']);
  }


  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Updates the metadata for the stored routine.
   */
  private function updateMetadata()
  {
    $this->myPhpStratumMetadata['routine_name'] = $this->myRoutineName;
    $this->myPhpStratumMetadata['designation']  = $this->myDesignationType;
    $this->myPhpStratumMetadata['table_name']   = $this->myTableName;
    $this->myPhpStratumMetadata['parameters']   = $this->myParameters;
    $this->myPhpStratumMetadata['columns']      = $this->myColumns;
    $this->myPhpStratumMetadata['fields']       = $this->myFields;
    $this->myPhpStratumMetadata['column_types'] = $this->myColumnsTypes;
    $this->myPhpStratumMetadata['timestamp']    = $this->myMTime;
    $this->myPhpStratumMetadata['replace']      = $this->myReplace;
    $this->myPhpStratumMetadata['phpdoc']       = $this->myDocBlockPartsWrapper;
    $this->myPhpStratumMetadata['spec_params']  = $this->myExtendedParameters;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Update information about specific parameters of stored routine.
   *
   * @throws \Exception
   */
  private function updateParametersInfo()
  {
    if (!empty($this->myExtendedParameters))
    {
      foreach ($this->myExtendedParameters as $spec_param_name => $spec_param_info)
      {
        $param_not_exist = true;
        foreach ($this->myParameters as $key => $param_info)
        {
          if ($param_info['name']==$spec_param_name)
          {
            $this->myParameters[$key] = array_merge($this->myParameters[$key], $spec_param_info);
            $param_not_exist          = false;
            break;
          }
        }
        if ($param_not_exist)
        {
          throw new RuntimeException("Specific parameter '%s' does not exist in file '%s'.",
                                     $spec_param_name,
                                     $this->mySourceFilename);
        }
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Validates the parameters found the DocBlock in the source of the stored routine against the parameters from the
   * metadata of MySQL and reports missing and unknown parameters names.
   */
  private function validateParameterLists()
  {
    // Make list with names of parameters used in database.
    $database_parameters_names = [];
    foreach ($this->myParameters as $parameter_info)
    {
      $database_parameters_names[] = $parameter_info['name'];
    }

    // Make list with names of parameters used in dock block of routine.
    $doc_block_parameters_names = [];
    if (isset($this->myDocBlockPartsSource['parameters']))
    {
      foreach ($this->myDocBlockPartsSource['parameters'] as $parameter)
      {
        $doc_block_parameters_names[] = $parameter['name'];
      }
    }

    // Check and show warning if any parameters is missing in doc block.
    $tmp = array_diff($database_parameters_names, $doc_block_parameters_names);
    foreach ($tmp as $name)
    {
      echo sprintf("  Warning: parameter '%s' is missing from doc block.\n", $name);
    }

    // Check and show warning if find unknown parameters in doc block.
    $tmp = array_diff($doc_block_parameters_names, $database_parameters_names);
    foreach ($tmp as $name)
    {
      echo sprintf("  Warning: unknown parameter '%s' found in doc block.\n", $name);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
