<?php
/**
 * This file is part of mundschenk-at/wp-data-storage.
 *
 * Copyright 2017-2018 Peter Putzer.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or ( at your option ) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * @package mundschenk-at/wp-data-storage/tests
 * @license http://www.gnu.org/licenses/gpl-2.0.html
 */

namespace Mundschenk\Data_Storage\Tests;

use Mundschenk\Data_Storage\Transients;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;

use Mockery as m;

/**
 * Mundschenk\Data_Storage\Transients unit test for the singleton methods.
 *
 * @coversDefaultClass \Mundschenk\Data_Storage\Transients
 * @usesDefaultClass \Mundschenk\Data_Storage\Transients
 *
 * @uses ::__construct
 * @uses \Mundschenk\Data_Storage\Abstract_Cache::__construct
 */
class Transients_Test extends TestCase {

	const PREFIX          = 'my_prefix_';
	const INCREMENTOR_KEY = self::PREFIX . 'transients_incrementor';

	/**
	 * Test fixture.
	 *
	 * @var \Mundschenk\Data_Storage\Transients
	 */
	protected $transients;

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp() { // @codingStandardsIgnoreLine
		parent::setUp();

		$this->transients = m::mock( Transients::class )->shouldAllowMockingProtectedMethods()->makePartial()
			->shouldReceive( 'get' )->once()->with( self::INCREMENTOR_KEY, true )->andReturn( 999 )
			->getMock();
		$this->transients->__construct( self::PREFIX );
	}

	/**
	 * Necesssary clean-up work.
	 */
	protected function tearDown() { // @codingStandardsIgnoreLine
		parent::tearDown();
	}

	/**
	 * Tests constructor.
	 *
	 * @covers ::__construct
	 *
	 * @uses \Mundschenk\Data_Storage\Abstract_Cache::__construct
	 */
	public function test___construct() {
		$transients = m::mock( Transients::class )->shouldAllowMockingProtectedMethods()->makePartial()
			->shouldReceive( 'get' )->once()->with( 'some_prefix_transients_incrementor', true )->andReturn( 0 )
			->shouldReceive( 'invalidate' )->once()
			->getMock();
		$transients->__construct( 'some_prefix_' );

		$this->assertInstanceOf( Transients::class, $transients );
	}

	/**
	 * Provides data for testing invalidate.
	 *
	 * @return array
	 */
	public function provide_invalidate_data() {
		return [
			[ [ 'bar', 'baz' ], true ],
			[ [ 'bar', 'baz' ], false ],
		];
	}

	/**
	 * Tests invalidate.
	 *
	 * @dataProvider provide_invalidate_data
	 *
	 * @covers ::invalidate
	 *
	 * @uses ::get_key
	 *
	 * @param string[] $expected_keys        An array of transient keys.
	 * @param bool     $object_cache_enabled The result of wp_using_ext_object_cache().
	 */
	public function test_invalidate( $expected_keys, $object_cache_enabled ) {

		if ( ! $object_cache_enabled ) {
			$this->transients->shouldReceive( 'get_keys_from_database' )->once()->andReturn( $expected_keys );

			foreach ( $expected_keys as $raw_key ) {
				$this->transients->shouldReceive( 'delete' )->once()->with( $raw_key, true );
			}
		}

		$this->transients->shouldReceive( 'set' )->once()->with( self::INCREMENTOR_KEY, m::type( 'int' ), 0, true );
		Functions\expect( 'wp_using_ext_object_cache' )->once()->andReturn( $object_cache_enabled );

		$this->transients->invalidate();

		$this->assertTrue( true );
	}

	/**
	 * Tests get_keys_from_database.
	 *
	 * @covers ::get_keys_from_database
	 */
	public function test_get_keys_from_database() {
		$dummy_result = [ [ 'option_name' => Transients::TRANSIENT_SQL_PREFIX . 'typo_foobar' ] ];

		global $wpdb;

		if ( ! defined( 'ARRAY_A' ) ) {
			define( 'ARRAY_A', 'array' );
		}

		$wpdb          = m::mock( 'wpdb' ); // WPCS: override ok.
		$wpdb->options = 'wp_options';
		$this->transients->shouldReceive( 'get_prefix' )->once()->andReturn( self::PREFIX );
		$wpdb->shouldReceive( 'prepare' )->with( m::type( 'string' ), Transients::TRANSIENT_SQL_PREFIX . self::PREFIX . '%' )->andReturn( 'fake SQL string' );
		$wpdb->shouldReceive( 'get_results' )->with( 'fake SQL string', ARRAY_A )->andReturn( $dummy_result );

		Functions\expect( 'wp_list_pluck' )->once()->with( $dummy_result, 'option_name' )->andReturn( [ 'typo_foobar' ] );

		$this->assertSame( [ 'typo_foobar' ], $this->transients->get_keys_from_database() );
	}

	/**
	 * Tests get.
	 *
	 * @covers ::get
	 */
	public function test_get() {
		$raw_key = 'foo';
		$key     = 'bar_foo';

		$this->transients->shouldReceive( 'get_key' )->once()->with( $raw_key )->andReturn( $key );

		Functions\expect( 'get_transient' )->once()->with( $key )->andReturn( 'bar' );

		$this->assertSame( 'bar', $this->transients->get( $raw_key ) );
	}

	/**
	 * Tests set.
	 *
	 * @covers ::set
	 */
	public function test_set() {
		$value    = 'bar';
		$raw_key  = 'foo';
		$key      = 'bar_foo';
		$duration = 99;

		$this->transients->shouldReceive( 'get_key' )->once()->with( $raw_key )->andReturn( $key );

		Functions\expect( 'set_transient' )->once()->with( $key, $value, $duration )->andReturn( true );

		$this->assertTrue( $this->transients->set( $raw_key, $value, $duration ) );
	}

	/**
	 * Tests set_large_object. Can't test the failing branch.
	 *
	 * @covers ::set_large_object
	 */
	public function test_set_large_object() {
		$value    = new \stdClass();
		$raw_key  = 'foo';
		$duration = 99;

		$this->transients->shouldReceive( 'set' )->once()->with( $raw_key, m::type( 'string' ), $duration )->andReturn( true );

		$this->assertTrue( $this->transients->set_large_object( $raw_key, $value, $duration ) );
	}

	/**
	 * Tests get_large_object.
	 *
	 * @covers ::get_large_object
	 */
	public function test_get_large_object() {
		$raw_key = 'foo';

		$this->transients->shouldReceive( 'get' )->once()->with( $raw_key )->andReturn( \base64_encode( \gzencode( \serialize( new \stdClass() ) ) ) ); // @codingStandardsIgnoreLine
		$this->transients->shouldReceive( 'maybe_fix_object' )->once()->with( m::type( \stdClass::class ) )->andReturnUsing(
			function( $object ) {
				return $object;
			}
		);

		$this->assertInstanceOf( \stdClass::class, $this->transients->get_large_object( $raw_key ) );
	}

	/**
	 * Tests get_large_object (but not found).
	 *
	 * @covers ::get_large_object
	 */
	public function test_get_large_object_not_found() {
		$raw_key = 'foo';

		$this->transients->shouldReceive( 'get' )->once()->with( $raw_key )->andReturn( false );

		$this->assertFalse( $this->transients->get_large_object( $raw_key ) );
	}

	/**
	 * Tests get_large_object with failing uncompress.
	 *
	 * @covers ::get_large_object
	 */
	public function test_get_large_object_uncompression_failing() {
		$raw_key = 'foo';

		$this->transients->shouldReceive( 'get' )->once()->with( $raw_key )->andReturn( \base64_encode( \serialize( new \stdClass() ) ) ); // @codingStandardsIgnoreLine

		$this->assertFalse( $this->transients->get_large_object( $raw_key ) );
	}

	/**
	 * Tests delete.
	 *
	 * @covers ::delete
	 *
	 * @uses ::get_key
	 */
	public function test_delete() {
		$raw_key = 'foo';
		$key     = $this->invokeMethod( $this->transients, 'get_key', [ $raw_key ] );

		Functions\expect( 'delete_transient' )->once()->with( $key )->andReturn( true );

		$this->assertTrue( $this->transients->delete( $raw_key ) );
	}

	/**
	 * Test maybe_fix_object.
	 *
	 * @covers ::maybe_fix_object
	 */
	public function test_maybe_fix_object() {
		$fake_object_string = 'O:16:"SomeMissingClass":1:{s:1:"a";s:1:"b";}';
		$fake_object        = unserialize( $fake_object_string ); // @codingStandardsIgnoreLine

		// Unfortunately, serialize and  unserialize cannot be mocked.
		$object = $this->invokeMethod( $this->transients, 'maybe_fix_object', [ $fake_object ] );

		$this->assertTrue( $object !== $fake_object );
	}
}
