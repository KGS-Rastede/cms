<?php

namespace Statamic\Taxonomies;

use ArrayAccess;
use Facades\Statamic\View\Cascade;
use Illuminate\Contracts\Support\Responsable;
use Statamic\Contracts\Data\Augmentable;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Data\Augmentable as AugmentableTrait;
use Statamic\Data\Publishable;
use Statamic\Facades\Site;
use Statamic\Http\Responses\DataResponse;
use Statamic\Revisions\Revisable;
use Statamic\Routing\Routable;

class LocalizedTerm implements Term, ArrayAccess, Responsable, Augmentable
{
    use Revisable, Routable, Publishable, AugmentableTrait;

    protected $locale;
    protected $term;

    public function __construct($term, $locale)
    {
        $this->term = $term;
        $this->locale = $locale;
    }

    public function get($key, $fallback = null)
    {
        return $this->data()->get($key, $fallback);
    }

    public function set($key, $value)
    {
        $data = $this->data();

        $data->put($key, $value);

        return $this->data($data);
    }

    public function has($key)
    {
        return $this->get($key) != null;
    }

    public function data($data = null)
    {
        if (func_num_args() === 0) {
            return $this->term->dataForLocale($this->locale);
        }

        $this->term->dataForLocale($this->locale, $data);

        return $this;
    }

    public function merge($data)
    {
        $this->data($this->data()->merge($data));

        return $this;
    }

    public function values()
    {
        return $this->term
            ->dataForLocale($this->defaultLocale())
            ->merge($this->data());
    }

    public function value($key)
    {
        return $this->get($key) ?? $this->inDefaultLocale()->get($key);
    }

    public function site()
    {
        return Site::get($this->locale);
    }

    public function title()
    {
        return $this->value('title') ?? $this->slug();
    }

    public function slug($slug = null)
    {
        if (func_num_args() === 1) {
            if ($this->isDefaultLocale()) {
                $this->term->slug($slug);
            } elseif ($this->term->slug() !== $slug) {
                $this->set('slug', $slug);
            }
            return $this;
        }

        return $this->get('slug') ?? $this->term->slug();
    }

    protected function defaultLocale()
    {
        return $this->taxonomy()->sites()->first();
    }

    public function inDefaultLocale()
    {
        return $this->in($this->defaultLocale());
    }

    protected function isDefaultLocale()
    {
        return $this->defaultLocale() === $this->locale;
    }

    public function hasOrigin()
    {
        return !$this->isDefaultLocale();
    }

    public function id()
    {
        return $this->term->id();
    }

    public function taxonomy($taxonomy = null)
    {
        if (func_num_args() === 0) {
            return $this->term->taxonomy();
        }

        $this->term->taxonomy($taxonomy);

        return $this;
    }

    public function taxonomyHandle()
    {
        return $this->term->taxonomyHandle();
    }

    public function collection($collection = null)
    {
        if (func_num_args() === 0) {
            return $this->term->collection();
        }

        $this->term->collection($collection);

        return $this;
    }

    public function blueprint()
    {
        return $this->term->blueprint();
    }

    public function reference()
    {
        return $this->term->reference();
    }

    public function in($site)
    {
        return $this->term->in($site);
    }

    public function queryEntries()
    {
        return $this->term->queryEntries();
    }

    public function entries()
    {
        return $this->term->entries();
    }

    protected function revisionKey()
    {
        return vsprintf('taxonomies/%s/%s/%s', [
            $this->taxonomyHandle(),
            $this->locale(),
            $this->slug()
        ]);
    }

    protected function revisionAttributes()
    {
        return [
            'id' => $this->id(),
            'slug' => $this->slug(),
            'published' => $this->published(),
            'data' => $this->data()->except(['updated_by', 'updated_at'])->all(),
        ];
    }

    public function makeFromRevision($revision)
    {
        $entry = clone $this;

        if (! $revision) {
            return $entry;
        }

        $attrs = $revision->attributes();

        return $entry
            ->published($attrs['published'])
            ->data($attrs['data'])
            ->slug($attrs['slug']);
    }

    public function origin()
    {
        return $this->inDefaultLocale();
    }

    public function isRoot()
    {
        return $this->isDefaultLocale();
    }

    public function locale()
    {
        return $this->locale;
    }

    public function revisionsEnabled($enabled = null)
    {
        if (func_num_args() === 0) {
            return $this->term->revisionsEnabled();
        }

        $this->term->revisionsEnabled($enabled);

        return $this;
    }

    public function editUrl()
    {
        return $this->cpUrl('taxonomies.terms.edit');
    }

    public function updateUrl()
    {
        return $this->cpUrl('taxonomies.terms.update');
    }

    public function publishUrl()
    {
        return $this->cpUrl('taxonomies.terms.published.store');
    }

    public function unpublishUrl()
    {
        return $this->cpUrl('taxonomies.terms.published.destroy');
    }

    public function revisionsUrl()
    {
        return $this->cpUrl('taxonomies.terms.revisions.index');
    }

    public function createRevisionUrl()
    {
        return $this->cpUrl('taxonomies.terms.revisions.store');
    }

    public function restoreRevisionUrl()
    {
        return $this->cpUrl('taxonomies.terms.restore-revision');
    }

    public function livePreviewUrl()
    {
        return $this->cpUrl('taxonomies.terms.preview.edit');
    }

    protected function cpUrl($route)
    {
        return cp_route($route, [$this->taxonomyHandle(), $this->inDefaultLocale()->slug(), $this->locale()]);
    }

    public function offsetExists($key)
    {
        return $this->has($key);
    }

    public function offsetGet($key)
    {
        return $this->value($key);
    }

    public function offsetSet($key, $value)
    {
        $this->set($key, $value);
    }

    public function offsetUnset($key)
    {
        $this->remove($key);
    }

    public function route()
    {
        $route = '/' . str_replace('_', '-', $this->taxonomyHandle()) . '/{slug}';

        if ($this->collection()) {
            $route = $this->collection()->url() . $route;
        }

        return $route;
    }

    public function routeData()
    {
        return $this->values()->merge([
            'id' => $this->id(),
            'slug' => $this->slug(),
        ])->all();
    }

    public function toResponse($request)
    {
        return (new DataResponse($this))->toResponse($request);
    }

    public function toLivePreviewResponse($request, $extras)
    {
        Cascade::set('live_preview', $extras);

        return $this->toResponse($request);
    }

    public function template($template = null)
    {
        if (func_num_args() === 0) {
            return $this->get('template')
                ?? config('statamic.theming.views.term'); // todo: get the fallback template from the collection
        }

        return $this->set('template', $template);
    }

    public function layout($layout = null)
    {
        if (func_num_args() === 0) {
            return $this->get('layout', config('statamic.theming.views.layout'));
        }

        return $this->set('layout', $layout);
    }

    public function augmentedArrayData()
    {
        return $this->values()->merge([
            'id' => $this->id(),
            'slug' => $this->slug(),
            'uri' => $this->uri(),
            'url' => $this->url(),
            'title' => $this->title(),
            'entries' => $entryQuery = $this->queryEntries()->where('site', $this->locale),
            'entries_count' => $entryQuery->count(),
        ])->all();
    }

    public function save()
    {
        return $this->term->save();
    }

    public function delete()
    {
        return $this->term->delete();
    }

    public function private()
    {
        return false;
    }

    public function path()
    {
        return $this->term->path();
    }
}