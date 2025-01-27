<?php

namespace Statamic\Http\View\Composers;

use Facades\Statamic\Fields\FieldtypeRepository;
use Illuminate\View\View;
use Statamic\Facades\Preference;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Statamic;

class JavascriptComposer
{
    const VIEWS = ['statamic::partials.scripts'];

    public function compose(View $view)
    {
        $user = User::current();

        Statamic::provideToScript([
            'version' => Statamic::version(),
            'laravelVersion' => app()->version(),
            'csrfToken' => csrf_token(),
            'cpRoot' => str_start(config('statamic.cp.route'), '/'),
            'urlPath' => '/' . request()->path(),
            'resourceUrl' => Statamic::assetUrl(),
            'locales' => config('statamic.system.locales'),
            'flash' => Statamic::flash(),
            'ajaxTimeout' => config('statamic.system.ajax_timeout'),
            'googleDocsViewer' => config('statamic.assets.google_docs_viewer'),
            'user' => $this->user($user),
            'paginationSize' => config('statamic.cp.pagination_size'),
            'translationLocale' => app('translator')->locale(),
            'translations' => app('translator')->toJson(),
            'sites' => $this->sites(),
            'selectedSite' => Site::selected()->handle(),
            'ampEnabled' => config('statamic.amp.enabled'),
            'preloadableFieldtypes' => FieldtypeRepository::preloadable()->keys(),
            'livePreview' => config('statamic.live_preview'),
            'locale' => config('app.locale'),
            'permissions' => $this->permissions($user)
        ]);
    }

    protected function sites()
    {
        return Site::all()->map(function ($site) {
            return [
                'name' => $site->name(),
                'handle' => $site->handle(),
            ];
        })->values();
    }

    protected function permissions($user)
    {
        $permissions = $user ? $user->permissions() : [];

        return base64_encode(json_encode($permissions));
    }

    protected function user($user)
    {
        if (! $user) {
            return [];
        }

        return array_merge($user->augmentedArrayData(), [
            'preferences' => Preference::all(),
            'permissions' => $user->permissions()->all(),
        ]);
    }
}
