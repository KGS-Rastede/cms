<?php

namespace Tests\Fields\Fieldtypes;

use Tests\TestCase;
use Statamic\Fields\Field;
use Statamic\Facades\Fieldset;
use Statamic\Fields\Fieldtype;
use Statamic\Fieldtypes\NestedFields;
use Facades\Statamic\Fields\FieldRepository;
use Facades\Statamic\Fields\FieldtypeRepository;

class NestedFieldsTest extends TestCase
{
    /** @test */
    function it_preprocesses_each_value_when_used_for_config()
    {
        FieldtypeRepository::shouldReceive('find')
            ->with('assets')
            ->andReturn(new class extends Fieldtype {
                protected $component = 'assets';
                protected $configFields = [
                    'max_files' => ['type' => 'integer'],
                    'container' => ['type' => 'plain']
                ];
            });

        FieldtypeRepository::shouldReceive('find')
            ->with('plain')
            ->andReturn(new class extends Fieldtype {
                public function preProcess($data) {
                    return $data;
                }
            });

        FieldtypeRepository::shouldReceive('find')
            ->with('integer')
            ->andReturn(new class extends Fieldtype {
                public function preProcess($data) {
                    return (int) $data;
                }
            });

        FieldRepository::shouldReceive('find')
            ->with('testfieldset.image')
            ->andReturnUsing(function () {
                return new Field('image', [
                    'type' => 'assets',
                    'max_files' => '2', // corresponding fieldtype has preprocessing
                    'container' => 'main', // corresponding fieldtype has no preprocessing
                    'foo' => 'bar' // no corresponding fieldtype, so theres no preprocessing
                ]);
            });

        $actual = (new NestedFields)->preProcessConfig([
            [
                'handle' => 'image',
                'field' => 'testfieldset.image',
                'config' => [
                    'display' => 'Test Image Field',
                    'instructions' => 'Some instructions',
                    'validate' => 'required'
                ]
            ]
        ]);

        $this->assertSame([
            [
                'type' => 'assets',
                'max_files' => 2,
                'container' => 'main',
                'foo' => 'bar',
                'display' => 'Test Image Field',
                'instructions' => 'Some instructions',
                'validate' => 'required',
                'component' => 'assets',
                'handle' => 'image',
                'required' => true,
            ]
        ], $actual);
    }

    /** @test */
    function it_preprocesses_from_blueprint_format_to_vue()
    {
        $testFieldset = Fieldset::make('test')->setContents(['fields' => [
            'bar' => ['type' => 'text']
        ]]);
        Fieldset::shouldReceive('all')->andReturn(collect([$testFieldset]));

        $actual = (new NestedFields)->preProcess([
            [
                'handle' => 'one',
                'field' => [
                    'type' => 'plain',
                    'display' => 'First Field',
                ]
            ],
            [
                'handle' => 'two',
                'field' => 'test.bar',
                'config' => [
                    'width' => 50,
                    'display' => 'Second Field',
                ]
            ],
            [
                'import' => 'test',
                'prefix' => 'foo',
            ],
            [
                'import' => 'test',
            ]
        ]);

        $this->assertSame([
            [
                'handle' => 'one',
                'type' => 'inline',
                'config' => [
                    'type' => 'plain',
                    'display' => 'First Field',
                    'width' => 100,
                ],
                'fieldtype' => 'plain',
                '_id' => 0,
            ],
            [
                'handle' => 'two',
                'type' => 'reference',
                'field_reference' => 'test.bar',
                'config' => [
                    'placeholder' => null,
                    'character_limit' => 0,
                    'prepend' => null,
                    'append' => null,
                    'component' => 'text',
                    'width' => 50,
                    'display' => 'Second Field',
                ],
                'config_overrides' => ['width', 'display'],
                'fieldtype' => 'text',
                '_id' => 1,
            ],
            [
                'type' => 'import',
                'fieldset' => 'test',
                'prefix' => 'foo',
                '_id' => 2,
            ],
            [
                'type' => 'import',
                'fieldset' => 'test',
                'prefix' => null,
                '_id' => 3,
            ]
        ], $actual);
    }

    /** @test */
    function it_processes_from_vue_to_blueprint_format()
    {
        $actual = (new NestedFields)->process([
            [
                '_id' => 'id-1',
                'handle' => 'one',
                'type' => 'inline',
                'fieldtype' => 'plain',
                'config' => [
                    'type' => 'plain',
                    'instructions' => null,
                    'width' => 100,
                    'display' => 'First Field',
                ],
            ],
            [
                '_id' => 'id-2',
                'handle' => 'two',
                'type' => 'reference',
                'field_reference' => 'foo.bar',
                'fieldtype' => 'text',
                'config' => [
                    'instructions' => null,
                    'width' => 50,
                    'display' => 'Second Field'
                ],
                'config_overrides' => ['display', 'width']
            ],
            [
                '_id' => 'id-3',
                'type' => 'import',
                'fieldset' => 'test',
                'prefix' => 'foo'
            ],
            [
                '_id' => 'id-4',
                'type' => 'import',
                'fieldset' => 'test',
                'prefix' => null,
            ]
        ]);

        $this->assertSame([
            [
                'handle' => 'one',
                'field' => [
                    'type' => 'plain',
                    'display' => 'First Field',
                ]
            ],
            [
                'handle' => 'two',
                'field' => 'foo.bar',
                'config' => [
                    'width' => 50,
                    'display' => 'Second Field',
                ]
            ],
            [
                'import' => 'test',
                'prefix' => 'foo',
            ],
            [
                'import' => 'test',
            ]
        ], $actual);
    }
}
