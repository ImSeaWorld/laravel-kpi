<?php

namespace Finller\Kpi;

use Error;
use Carbon\Carbon;

use Finller\Kpi\{
    Enums\KpiInterval,
    Database\Factories\KpiFactory,
};
use Finller\Kpi\Adapters\{
    MySqlAdapter,
    SqliteAdapter,
    AbstractAdapter,
};

use Illuminate\Support\{
    Str,
    Facades\DB
};
use Illuminate\Database\Eloquent\{
    Model,
    Builder,
    Factories\Factory,
    Factories\HasFactory,
    Casts\ArrayObject,
    Casts\AsArrayObject,
};

/**
 * @property string $key
 * @property ?string $description
 * @property ?string $string_value
 * @property ?float $number_value
 * @property ?array $json_value
 * @property ?int $money_value
 * @property ?string $money_currency
 * @property ?ArrayObject $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Kpi extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'string_value',
        'number_value',
        'money_value',
        'money_currency',
        'json_value',
        'description',
        'metadata',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'number_value' => 'float',
        'json_value' => 'array',
        'metadata' => AsArrayObject::class,
    ];

    public function scopeBetween(Builder $query, Carbon $start, Carbon $end)
    {
        return $query
            ->where('kpis.created_at', '>=', $start)
            ->where('kpis.created_at', '<=', $end);
    }

    public function scopeAfter(Builder $query, Carbon $date)
    {
        return $query->where('kpis.created_at', '>=', $date);
    }

    public function scopeBefore(Builder $query, Carbon $date)
    {
        return $query->where('kpis.created_at', '<=', $date);
    }

    public function scopePerInterval(Builder $query, KpiInterval $interval)
    {
        // find what key is searched for
        $key = collect($query->getQuery()->wheres)
            ->whereIn('column', ['kpis.key', 'key'])
            ->pluck('value')
            ->first();

        //only get most recent kpi for each day
        return $query->whereIn('kpis.id', function ($q) use ($key, $query, $interval) {
            $q
                ->from($query->getQuery()->from)
                ->when($key, fn ($b) => $b->where('kpis.key', $key))
                ->select(DB::raw('max(kpis.id) as id'))
                ->groupBy($this->getSqlDateAdapter($query)->groupBy('kpis.created_at', $interval->value));
        });
    }

    public function scopePerDay(Builder $query)
    {
        return $query->perInterval(KpiInterval::Day); // @phpstan-ignore-line
    }

    public function scopePerWeek(Builder $query)
    {
        return $query->perInterval(KpiInterval::Week); // @phpstan-ignore-line
    }

    public function scopePerMonth(Builder $query)
    {
        return $query->perInterval(KpiInterval::Month); // @phpstan-ignore-line
    }

    public function scopePerYear(Builder $query)
    {
        return $query->perInterval(KpiInterval::Year); // @phpstan-ignore-line
    }

    protected function getSqlDateAdapter(Builder $builder): AbstractAdapter
    {
        $driver = $builder->getConnection()->getDriverName(); // @phpstan-ignore-line

        return match ($driver) {
            'mysql' => new MySqlAdapter(),
            'sqlite' => new SqliteAdapter(),
                // 'pgsql' => new PgsqlAdapter(),
            default => throw new Error("Unsupported database driver : {$driver}."),
        };
    }

    public function newCollection(array $models = [])
    {
        $model = config('kpi.kpi_collection_model');

        return new $model($models);
    }

    protected static function newFactory(): Factory
    {
        return KpiFactory::new();
    }

    public function toDifference(?Kpi $kpi, bool $relative = false): static
    {
        // @phpstan-ignore-next-line
        return new static([
            'number_value' => $this->toNumberDifference($kpi?->number_value, $relative),
            'money_value' => $this->toMoneyDifference($kpi?->money_value, $relative),
        ]);
    }

    protected function toNumberDifference(float|int|null $value, bool $relative = false): ?float
    {
        if (!$this->number_value || $value === null) {
            return null;
        }

        if ($relative) {
            return (floatval($this->number_value) - floatval($value)) / floatval($this->number_value);
        }

        return floatval($this->number_value) - floatval($value);
    }

    /**
     * @param  mixed  $value
     */
    protected function toMoneyDifference($value, bool $relative = false): ?float
    {
        return $this->toNumberDifference($value, $relative);
    }
}
