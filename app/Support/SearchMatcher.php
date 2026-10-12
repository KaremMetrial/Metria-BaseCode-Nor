<?php

namespace App\Support;

use App\Http\Requests\Shared\ListRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\SQLiteConnection;

/** Literal search first, then Arabic spelling fallback within the already filtered scope. */
final class SearchMatcher
{
    /** @template T of Model
     * @param  Builder<T>  $query
     * @return Builder<T>
     */
    public function apply(Builder $query, ListRequest $request, string $search): Builder
    {
        if (ArabicText::normalize($search) === '') {
            return $query->whereRaw('1 = 0');
        }
        $literal = fn (Builder $nested) => $this->matchFields($nested, $request, $search, 'literal');
        if (! ArabicText::hasArabicLetters($search) || (clone $query)->where($literal)->exists()) {
            return $query->where($literal);
        }
        $arabic = fn (Builder $nested) => $this->matchFields($nested, $request, $search, 'arabic');
        if ((! str_contains($search, 'ة') && ! str_contains($search, 'ه')) || (clone $query)->where($arabic)->exists()) {
            return $query->where($arabic);
        }

        return $query->where(fn (Builder $nested) => $this->matchFields($nested, $request, $search, 'ending'));
    }

    /** @template T of Model
     * @param  Builder<T>  $query
     */
    private function matchFields(Builder $query, ListRequest $request, string $search, string $mode): void
    {
        foreach ($request::SEARCH as $column => $matching) {
            $query->orWhere(function (Builder $field) use ($column, $matching, $search, $mode): void {
                if ($matching === 'exact') {
                    $field->where($field->qualifyColumn($column), $search);
                } else {
                    $this->text($field, $column, $search, $mode);
                }
            });
        }
        if ($request::TRANSLATED !== null) {
            $query->orWhereHas('translations', function (Builder $translation) use ($request, $search, $mode): void {
                if ($mode !== 'literal') {
                    $translation->where('locale', 'ar');
                }
                $this->text($translation, $request::TRANSLATED, $search, $mode);
            });
        }
    }

    /** @template T of Model
     * @param  Builder<T>  $query
     */
    private function text(Builder $query, string $column, string $search, string $mode): void
    {
        $expression = $query->getQuery()->getGrammar()->wrap($query->qualifyColumn($column));
        $bindings = [];
        if ($mode !== 'literal') {
            $connection = $query->getConnection();
            if ($connection instanceof SQLiteConnection) {
                $pdo = $connection->getReadPdo();
                // Avoid SQLite's parser depth limit from nested Unicode REPLACE calls.
                $pdo->sqliteCreateFunction('arabic_search_normalize', static fn (?string $value, int $ending): ?string => $value === null ? null : ArabicText::normalize($value, (bool) $ending), 2);
                $expression = "arabic_search_normalize($expression, ?)";
                $bindings[] = $mode === 'ending' ? 1 : 0;
            } elseif ($connection instanceof MySqlConnection && ! $connection->isMaria() && version_compare($connection->getServerVersion(), '8.0.4', '>=')) {
                $expression = "REGEXP_REPLACE($expression, ?, '')";
                $bindings[] = '['.implode('', ArabicText::ignoredCharacters()).']';
            } else {
                foreach (ArabicText::ignoredCharacters() as $character) {
                    $expression = "REPLACE($expression, ?, '')";
                    $bindings[] = $character;
                }
            }
            foreach (($connection instanceof SQLiteConnection ? [] : ArabicText::LETTERS + ($mode === 'ending' ? ['ة' => 'ه'] : [])) as $from => $to) {
                $expression = "REPLACE($expression, ?, ?)";
                $bindings[] = $from;
                $bindings[] = $to;
            }
            $search = ArabicText::normalize($search, $mode === 'ending');
        }
        $bindings[] = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
        $query->whereRaw("$expression LIKE ? ESCAPE '!'", $bindings);
    }

    /** Match published snapshots without examining draft translations. */
    public function matches(string $value, string $search, string $mode, ?string $locale = null): bool
    {
        if ($mode !== 'literal') {
            if (($locale !== null && $locale !== 'ar') || ! ArabicText::hasArabicLetters($search)) {
                return false;
            }
            $value = ArabicText::normalize($value, $mode === 'ending');
            $search = ArabicText::normalize($search, $mode === 'ending');
        }

        return ArabicText::normalize($search) !== '' && mb_stripos($value, $search) !== false;
    }
}
