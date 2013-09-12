<?php
namespace Aura\Sql\Connection;
use PDO;
use Aura\Sql\ConnectionFactory;
use Aura\Sql\ColumnFactory;
use Aura\Sql\Column;

/**
 * Test class for AbstractConnection.
 * Generated by PHPUnit on 2011-06-21 at 16:49:51.
 */
abstract class AbstractConnectionTest extends \PHPUnit_Framework_TestCase
{
    protected $extension;
    
    protected $dsn = [];
    
    protected $connection_params = array(
        'dsn'      => [],
        'username' => null,
        'password' => null,
        'options'  => [],
    );
    
    protected $expect_dsn_string;
    
    protected $connection_type;
    
    protected $connection;
    
    protected $schema1 = 'aura_test_schema1';
    
    protected $schema2 = 'aura_test_schema2';
    
    protected $table = 'aura_test_table';
    
    protected $expect_fetch_table_list;
    
    protected $expect_fetch_table_cols;
    
    protected $expect_quote_scalar;
    
    protected $expect_quote_array;
    
    protected $expect_quote_into;
    
    protected $expect_quote_into_many;
    
    protected $expect_quote_multi;
    
    protected $expect_select_query_class = 'Aura\Sql\Query\Select';
    
    protected $expect_delete_query_class = 'Aura\Sql\Query\Delete';
    
    protected $expect_insert_query_class = 'Aura\Sql\Query\Insert';
    
    protected $expect_update_query_class = 'Aura\Sql\Query\Update';
    
    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    public function setUp()
    {
        parent::setUp();
        
        // skip if we don't have the extension
        if (! extension_loaded($this->extension)) {
            $this->markTestSkipped("Extension '{$this->extension}' not loaded.");
        }
        
        // convert column arrays to objects
        foreach ($this->expect_fetch_table_cols as $name => $info) {
            $this->expect_fetch_table_cols[$name] = new Column(
                $info['name'],
                $info['type'],
                $info['size'],
                $info['scale'],
                $info['notnull'],
                $info['default'],
                $info['autoinc'],
                $info['primary']
            );
        }
        
        // load test config values
        $test_class = get_class($this);
        $this->connection_params = array_merge(
            $this->connection_params,
            $GLOBALS[$test_class]['connection_params']
        );
        
        $this->expect_dsn_string = $GLOBALS[$test_class]['expect_dsn_string'];
        
        $connection_factory = new ConnectionFactory;
        
        $this->connection = $connection_factory->newInstance(
            $this->connection_type,
            $this->connection_params['dsn'],
            $this->connection_params['username'],
            $this->connection_params['password'],
            $this->connection_params['options']
        );
        
        // database setup
        $db_setup_class = $GLOBALS[$test_class]['db_setup_class'];
        $this->db_setup = new $db_setup_class(
            $this->connection,
            $this->table,
            $this->schema1,
            $this->schema2
        );
        $this->db_setup->exec();
    }
    
    public function tearDown()
    {
        $this->connection->disconnect();
    }
    
    public function testGetProfiler()
    {
        $actual = $this->connection->getProfiler();
        $this->assertInstanceOf('\Aura\Sql\Profiler', $actual);
    }
    
    public function testGetColumnFactory()
    {
        $actual = $this->connection->getColumnFactory();
        $this->assertInstanceOf('\Aura\Sql\ColumnFactory', $actual);
    }
    
    public function testGetQueryFactory()
    {
        $actual = $this->connection->getQueryFactory();
        $this->assertInstanceOf('\Aura\Sql\Query\Factory', $actual);
    }
    
    public function testGetDsnString()
    {
        $actual = $this->connection->getDsnString();
        $this->assertEquals($this->expect_dsn_string, $actual);
    }
    
    public function testSetPdo()
    {
        $pdo = new PDO('sqlite::memory:');
        $this->connection->setPdo($pdo);
        $actual = $this->connection->getPdo();
        $this->assertSame($pdo, $actual);
    }
    
    public function testGetPdo()
    {
        $actual = $this->connection->getPdo();
        $this->assertInstanceOf('\PDO', $actual);
    }
    
    public function testQuery()
    {
        $text = "SELECT * FROM {$this->table}";
        $stmt = $this->connection->query($text);
        $this->assertInstanceOf('PDOStatement', $stmt);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $expect = 10;
        $actual = count($result);
        $this->assertEquals($expect, $actual);
    }
    
    public function testQueryWithData()
    {
        $text = "SELECT * FROM {$this->table} WHERE id <= :val";
        $bind['val'] = '5';
        $stmt = $this->connection->query($text, $bind);
        $this->assertInstanceOf('PDOStatement', $stmt);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $expect = 5;
        $actual = count($result);
        $this->assertEquals($expect, $actual);
    }
    
    public function testQueryWithArrayData()
    {
        $text = "SELECT * FROM {$this->table} WHERE id IN (:list) OR id = :id";
        
        $bind['list'] = [1, 2, 3, 4];
        $bind['id'] = 5;
        
        $stmt = $this->connection->query($text, $bind);
        $this->assertInstanceOf('PDOStatement', $stmt);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $expect = 5;
        $actual = count($result);
        $this->assertEquals($expect, $actual);
    }
    
    public function testQueryWithSelect()
    {
        $select = $this->connection->newSelect();
        
        $select->cols(['*'])
               ->from($this->table);
        
        $stmt = $this->connection->query($select);
        
        $this->assertInstanceOf('PDOStatement', $stmt);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $expect = 10;
        $actual = count($result);
        $this->assertEquals($expect, $actual);
    }
    
    public function testPrepareWithQuotedStringsAndData()
    {
        $text = "SELECT * FROM {$this->table}
                 WHERE 'leave :foo alone'
                 AND id IN (:list)
                 AND \"leave :bar alone\"";
        
        $bind = [
            'list' => [1, 2, 3, 4, 5],
            'foo' => 'WRONG',
            'bar' => 'WRONG',
        ];
        
        $stmt = $this->connection->prepare($text, $bind);
        
        $expect = str_replace(':list', '1, 2, 3, 4, 5', $text);
        $actual = $stmt->queryString;
        $this->assertSame($expect, $actual);
    }

    public function testFetchAll()
    {
        $text = "SELECT * FROM {$this->table}";
        $result = $this->connection->fetchAll($text);
        $expect = 10;
        $actual = count($result);
        $this->assertEquals($expect, $actual);
    }
    
    public function testFetchAllWithCallback()
    {
        $text = "SELECT * FROM {$this->table} ORDER BY id LIMIT 5";
        $result = $this->connection->fetchAll($text, [], function ($row) {
            return [strtolower($row['name'])];
        });
        $expect = 5;
        $actual = count($result);
        $this->assertEquals($expect, $actual);
        $expect = [
            ['anna'],
            ['betty'],
            ['clara'],
            ['donna'],
            ['fiona'],
        ];
        $this->assertEquals($expect, $result);
    }

    public function testFetchAssoc()
    {
        $text = "SELECT * FROM {$this->table} ORDER BY id";
        $result = $this->connection->fetchAssoc($text);
        $expect = 10;
        $actual = count($result);
        $this->assertEquals($expect, $actual);
        
        // 1-based IDs, not 0-based sequential values
        $expect = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
        $actual = array_keys($result);
        $this->assertEquals($expect, $actual);
    }
    
    public function testFetchAssocWithCallback()
    {
        $text = "SELECT * FROM {$this->table} ORDER BY id LIMIT 5";
        $result = $this->connection->fetchAssoc($text, [], function ($row) {
            return [strtolower($row['name'])];
        });
        $expect = 5;
        $actual = count($result);
        $this->assertEquals($expect, $actual);
        
        // 1-based IDs, not 0-based sequential values
        $expect = [1, 2, 3, 4, 5];
        $actual = array_keys($result);
        $this->assertEquals($expect, $actual);        
        
        $expect = [
            ['anna'],
            ['betty'],
            ['clara'],
            ['donna'],
            ['fiona'],
        ];
        $actual = array_values($result);
        $this->assertEquals($expect, $actual);
    }

    public function testFetchCol()
    {
        $text = "SELECT id FROM {$this->table} ORDER BY id";
        $result = $this->connection->fetchCol($text);
        $expect = 10;
        $actual = count($result);
        $this->assertEquals($expect, $actual);
        
        // // 1-based IDs, not 0-based sequential values
        $expect = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'];
        $this->assertEquals($expect, $result);
    }

    public function testFetchColWithCallback()
    {
        $text = "SELECT id FROM {$this->table} ORDER BY id LIMIT 5";
        $result = $this->connection->fetchCol($text, [], function ($val) {
            return $val * 2;
        });
        $expect = 5;
        $actual = count($result);
        $this->assertEquals($expect, $actual);

        $expect = [2, 4, 6, 8, 10];
        $this->assertEquals($expect, $result);
    }

    public function testFetchValue()
    {
        $text = "SELECT id FROM {$this->table} WHERE id = 1";
        $actual = $this->connection->fetchValue($text);
        $expect = '1';
        $this->assertEquals($expect, $actual);
    }
    
    public function testFetchPairs()
    {
        $text = "SELECT id, name FROM {$this->table} ORDER BY id";
        $actual = $this->connection->fetchPairs($text);
        $expect = [
          1  => 'Anna',
          2  => 'Betty',
          3  => 'Clara',
          4  => 'Donna',
          5  => 'Fiona',
          6  => 'Gertrude',
          7  => 'Hanna',
          8  => 'Ione',
          9  => 'Julia',
          10 => 'Kara',
        ];
        $this->assertEquals($expect, $actual);
    }

    public function testFetchPairsWithCallback()
    {
        $text = "SELECT id, name FROM {$this->table} ORDER BY id";
        $actual = $this->connection->fetchPairs($text, [], function ($row) {
            return [(string) $row[0], strtolower($row[1])];
        });
        $expect = [
          '1'  => 'anna',
          '2'  => 'betty',
          '3'  => 'clara',
          '4'  => 'donna',
          '5'  => 'fiona',
          '6'  => 'gertrude',
          '7'  => 'hanna',
          '8'  => 'ione',
          '9'  => 'julia',
          '10' => 'kara',
        ];
        $this->assertSame($expect, $actual);
    }

    public function testFetchOne()
    {
        $text = "SELECT id, name FROM {$this->table} WHERE id = 1";
        $actual = $this->connection->fetchOne($text);
        $expect = [
            'id'   => '1',
            'name' => 'Anna',
        ];
        $this->assertEquals($expect, $actual);
    }
    
    public function testFetchTableList()
    {
        $actual = $this->connection->fetchTableList();
        $this->assertEquals($this->expect_fetch_table_list, $actual);
    }
    
    public function testFetchTableList_schema()
    {
        $actual = $this->connection->fetchTableList('aura_test_schema2');
        $this->assertEquals($this->expect_fetch_table_list_schema, $actual);
    }
    
    public function testFetchTableCols()
    {
        $actual = $this->connection->fetchTableCols($this->table);
        $expect = $this->expect_fetch_table_cols;
        ksort($actual);
        ksort($expect);
        $this->assertSame(count($expect), count($actual));
        foreach (array_keys($expect) as $name) {
            $this->assertEquals($expect[$name], $actual[$name]);
        }
    }
    
    public function testFetchTableCols_schema()
    {
        $actual = $this->connection->fetchTableCols("aura_test_schema2.{$this->table}");
        $expect = $this->expect_fetch_table_cols;
        ksort($actual);
        ksort($expect);
        $this->assertSame(count($expect), count($actual));
        foreach ($expect as $name => $info) {
            $this->assertEquals($expect[$name], $actual[$name]);
        }
    }
    
    public function testQuote()
    {
        // quote a scalar
        $actual = $this->connection->quote('"foo" bar \'baz\'');
        $this->assertEquals($this->expect_quote_scalar, $actual);
        
        // quote a number
        $actual = $this->connection->quote(123.456);
        $this->assertEquals(123.456, $actual);
        
        // quote a numeric
        $actual = $this->connection->quote('123.456');
        $this->assertEquals(123.456, $actual);
        
        // quote an array
        $actual = $this->connection->quote(array('"foo"', 'bar', "'baz'"));
        $this->assertEquals($this->expect_quote_array, $actual);
    }
    
    /**
     * @todo Implement testQuoteInto().
     */
    public function testQuoteInto()
    {
        // no placeholders
        $actual = $this->connection->quoteInto('foo = bar', "'zim'");
        $expect = 'foo = bar';
        $this->assertEquals($expect, $actual);
        
        // one placeholder, one value
        $actual = $this->connection->quoteInto("foo = ?", "'bar'");
        $this->assertEquals($this->expect_quote_into,$actual);
        
        // many placeholders, many values
        $actual = $this->connection->quoteInto("foo = ? AND zim = ?", ["'bar'", "'baz'"]);
        $this->assertEquals($this->expect_quote_into_many, $actual);
        
        // many placeholders, too many values
        $actual = $this->connection->quoteInto("foo = ? AND zim = ?", ["'bar'", "'baz'", "'gir'"]);
        $this->assertEquals($this->expect_quote_into_many, $actual);
    }
    
    /**
     * @todo Implement testQuoteMulti().
     */
    public function testQuoteMulti()
    {
        $where = array(
            'id = 1',
            'foo = ?' => 'bar',
            'zim IN(?)' => array('dib', 'gir', 'baz'),
        );
        $actual = $this->connection->quoteMulti($where, ' AND ');
        $this->assertEquals($this->expect_quote_multi, $actual);
    }
    
    /**
     * @todo Implement testQuoteName().
     */
    public function testQuoteName()
    {
        // table AS alias
        $actual = $this->connection->quoteName('table AS alias');
        $this->assertEquals($this->expect_quote_name_table_as_alias, $actual);
        
        // table.col AS alias
        $actual = $this->connection->quoteName('table.col AS alias');
        $this->assertEquals($this->expect_quote_name_table_col_as_alias, $actual);
        
        // table alias
        $actual = $this->connection->quoteName('table alias');
        $this->assertEquals($this->expect_quote_name_table_alias, $actual);
        
        // table.col alias
        $actual = $this->connection->quoteName('table.col alias');
        $this->assertEquals($this->expect_quote_name_table_col_alias, $actual);
        
        // plain old identifier
        $actual = $this->connection->quoteName('table');
        $this->assertEquals($this->expect_quote_name_plain, $actual);
        
        // star
        $actual = $this->connection->quoteName('*');
        $this->assertEquals('*', $actual);
        
        // star dot star
        $actual = $this->connection->quoteName('*.*');
        $this->assertEquals('*.*', $actual);
    }
    
    /**
     * @todo Implement testQuoteNamesIn().
     */
    public function testQuoteNamesIn()
    {
        $sql = "*, *.*, foo.bar, CONCAT('foo.bar', \"baz.dib\") AS zim";
        $actual = $this->connection->quoteNamesIn($sql);
        $this->assertEquals($this->expect_quote_names_in, $actual);
    }
    
    public function testInsertAndLastInsertId()
    {
        $cols = ['name' => 'Laura'];
        $actual = $this->connection->insert($this->table, $cols);
        
        // did we get the right last ID?
        $actual = $this->fetchLastInsertId();
        $expect = '11';
        $this->assertEquals($expect, $actual);
        
        // did it insert?
        $actual = $this->connection->fetchOne("SELECT id, name FROM {$this->table} WHERE id = 11");
        $expect = ['id' => '11', 'name' => 'Laura'];
        $this->assertEquals($actual, $expect);
    }
    
    protected function fetchLastInsertId()
    {
        return $this->connection->lastInsertId();
    }
    
    public function testUpdate()
    {
        $cols   = ['name' => 'Annabelle'];
        $where  = 'id = :id';
        $bind   = ['id' => 1];
        $actual = $this->connection->update($this->table, $cols, $where, $bind);
        
        // did it update?
        $actual = $this->connection->fetchOne("SELECT id, name FROM {$this->table} WHERE id = 1");
        $expect = ['id' => '1', 'name' => 'Annabelle'];
        $this->assertEquals($actual, $expect);
        
        // did anything else update?
        $actual = $this->connection->fetchOne("SELECT id, name FROM {$this->table} WHERE id = 2");
        $expect = ['id' => '2', 'name' => 'Betty'];
        $this->assertEquals($actual, $expect);
    }
    
    public function testDelete()
    {
        $where  = 'id = :id';
        $bind   = ['id' => 1];
        $actual = $this->connection->delete($this->table, $where, $bind);
        
        // did it delete?
        $actual = $this->connection->fetchOne("SELECT * FROM {$this->table} WHERE id = 1");
        $this->assertFalse($actual);
        
        // do we still have everything else?
        $actual = $this->connection->fetchAll("SELECT * FROM {$this->table}");
        $expect = 9;
        $this->assertEquals($expect, count($actual));
    }
    
    public function testTransactions()
    {
        // data
        $cols = ['name' => 'Laura'];

        // begin and rollback
        $this->connection->beginTransaction();
        $this->connection->insert($this->table, $cols);
        $actual = $this->connection->fetchAll("SELECT * FROM {$this->table}");
        $this->assertSame(11, count($actual));
        $this->connection->rollback();
        $actual = $this->connection->fetchAll("SELECT * FROM {$this->table}");
        $this->assertSame(10, count($actual));
        
        // begin and commit
        $this->connection->beginTransaction();
        $this->connection->insert($this->table, $cols);
        $actual = $this->connection->fetchAll("SELECT * FROM {$this->table}");
        $this->connection->commit();
        $this->assertSame(11, count($actual));
    }

    public function testNewSelect()
    {
        $query = $this->connection->newSelect();
        
        $this->assertEquals($this->expect_select_query_class, get_class($query));
    }
    
    public function testNewDelete()
    {
        $query = $this->connection->newDelete();
        
        $this->assertEquals($this->expect_delete_query_class, get_class($query));
    }
    
    public function testNewUpdate()
    {
        $query = $this->connection->newUpdate();
        
        $this->assertEquals($this->expect_update_query_class, get_class($query));
    }
    
    public function testNewInsert()
    {
        $query = $this->connection->newInsert();
        
        $this->assertEquals($this->expect_insert_query_class, get_class($query));
    }
}
