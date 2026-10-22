<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Tests\TestCase;

// fillable, casts и связи моделей смотрят в существующие таблицы и колонки: ловит опечатки
// и pivot с именем по умолчанию (product_promotion вместо promotion_product)
class SchemaMatchesModelsTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<class-string<Model>> */
    private function models(): array
    {
        $models = [];
        foreach (glob(app_path('Models/*.php')) ?: [] as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');
            if (is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract()) {
                $models[] = $class;
            }
        }

        return $models;
    }

    public function test_модели_найдены(): void
    {
        $this->assertContains(Promotion::class, $this->models());
        $this->assertGreaterThanOrEqual(10, count($this->models()));
    }

    public function test_таблицы_и_колонки_моделей_существуют(): void
    {
        foreach ($this->models() as $class) {
            $model = new $class;
            $table = $model->getTable();

            $this->assertTrue(Schema::hasTable($table), "{$class}: нет таблицы {$table}");

            $columns = array_unique([...$model->getFillable(), ...array_keys($model->getCasts())]);
            foreach ($columns as $column) {
                $this->assertTrue(Schema::hasColumn($table, $column), "{$class}: нет колонки {$table}.{$column}");
            }
        }
    }

    public function test_связи_моделей_смотрят_в_существующие_таблицы(): void
    {
        $checked = 0;

        foreach ($this->models() as $class) {
            foreach ($this->relations($class) as $name => $relation) {
                $where = "{$class}::{$name}()";
                $related = $relation->getRelated()->getTable();
                $this->assertTrue(Schema::hasTable($related), "{$where}: нет таблицы {$related}");

                if ($relation instanceof BelongsToMany) {
                    $pivot = $relation->getTable();
                    $this->assertTrue(Schema::hasTable($pivot), "{$where}: нет pivot-таблицы {$pivot}");
                    foreach ([$relation->getForeignPivotKeyName(), $relation->getRelatedPivotKeyName()] as $key) {
                        $this->assertTrue(Schema::hasColumn($pivot, $key), "{$where}: нет колонки {$pivot}.{$key}");
                    }
                } elseif ($relation instanceof BelongsTo) {
                    $table = $relation->getParent()->getTable();
                    $this->assertTrue(Schema::hasColumn($table, $relation->getForeignKeyName()), "{$where}: нет колонки {$table}.{$relation->getForeignKeyName()}");
                } elseif ($relation instanceof HasOneOrMany) {
                    $this->assertTrue(Schema::hasColumn($related, $relation->getForeignKeyName()), "{$where}: нет колонки {$related}.{$relation->getForeignKeyName()}");
                }

                $checked++;
            }
        }

        $this->assertGreaterThan(10, $checked, 'Связи не нашлись: проверьте, что у методов указан тип возврата');
    }

    /**
     * @param  class-string<Model>  $class
     * @return array<string, Relation<Model, Model, mixed>>
     */
    private function relations(string $class): array
    {
        $result = [];
        $model = new $class;

        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // У пакетов свои связи (spatie teams() при выключенных командах)
            if (! str_starts_with((string) $method->getFileName(), app_path())) {
                continue;
            }

            $type = $method->getReturnType();
            if ($method->isStatic() || $method->getNumberOfRequiredParameters() > 0
                || ! $type instanceof ReflectionNamedType || ! is_a($type->getName(), Relation::class, true)) {
                continue;
            }

            $result[$method->getName()] = $method->invoke($model);
        }

        return $result;
    }
}
