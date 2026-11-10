<?php

/*
 * This file is part of fof/categories
 *
 * Copyright (c) 2021 Alexander Skvortsov.
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\Categories;

use Flarum\Api\Context;
use Flarum\Api\Resource\UserResource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Tags\Api\Resource\TagResource;
use Flarum\Tags\Tag;
use FoF\Categories\Content\Categories;
use FoF\Categories\Unread;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->jsDirectory(__DIR__.'/js/dist/forum')
        ->css(__DIR__.'/resources/less/forum.less')
        ->route('/categories', 'categories', Categories::class),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/resources/less/admin.less'),

    (new Extend\Settings())
        ->default('fof-categories.child-bare-icon', true)
        ->default('fof-categories.unread-color', '#e8a234')
        ->default('fof-categories.unread-badge-text', 'new')
        ->default('fof-categories.unread-badge-color', '#ff6000')
        ->serializeToForum('categories.keepTagsNav', 'fof-categories.keep-tags-nav', 'boolval')
        ->serializeToForum('categories.fullPageDesktop', 'fof-categories.full-page-desktop', 'boolval')
        ->serializeToForum('categories.compactMobile', 'fof-categories.compact-mobile', 'boolval')
        ->serializeToForum('categories.parentRemoveIcon', 'fof-categories.parent-remove-icon', 'boolval')
        ->serializeToForum('categories.parentRemoveDescription', 'fof-categories.parent-remove-description', 'boolval')
        ->serializeToForum('categories.parentRemoveStats', 'fof-categories.parent-remove-stats', 'boolval')
        ->serializeToForum('categories.parentRemoveLastDiscussion', 'fof-categories.parent-remove-last-discussion', 'boolval')
        ->serializeToForum('categories.childBareIcon', 'fof-categories.child-bare-icon', 'boolval')
        ->serializeToForum('categories.unreadEnabled', 'fof-categories.unread-enabled', 'boolval')
        ->serializeToForum('categories.unreadIconGlow', 'fof-categories.unread-icon-glow', 'boolval')
        ->serializeToForum('categories.unreadTitleColor', 'fof-categories.unread-title-color', 'boolval')
        ->serializeToForum('categories.unreadDot', 'fof-categories.unread-dot', 'boolval')
        ->serializeToForum('categories.unreadBadge', 'fof-categories.unread-badge', 'boolval')
        ->serializeToForum('categories.unreadBadgeText', 'fof-categories.unread-badge-text', 'strval')
        ->registerLessConfigVar('fof-categories-unread-color', 'fof-categories.unread-color', fn ($value) => $value ?: '#e8a234')
        ->registerLessConfigVar('fof-categories-unread-badge-color', 'fof-categories.unread-badge-color', fn ($value) => $value ?: '#ff6000'),

    (new Extend\ApiResource(TagResource::class))
            ->fields(fn () => [
                Schema\Integer::make('postCount')
                    ->get(function (\Flarum\Tags\Tag $tag, Context $context) {
                        $settings = resolve(SettingsRepositoryInterface::class);
                        if ($settings->get('fof-categories.small-forum-optimized', false)) {
                            return (int) $tag->discussions()
                                ->whereVisibleTo($context->getActor())
                                ->sum('comment_count');
                        }

                        // `post_count` is added to the `tags` table by this extension's
                        // migration, so it is not a declared property on core's Tag model.
                        return (int) $tag->getAttribute('post_count');
                    }),
                Schema\Integer::make('discussionCount')
                    ->get(function (\Flarum\Tags\Tag $tag, Context $context) {
                        $settings = resolve(SettingsRepositoryInterface::class);
                        if ($settings->get('fof-categories.small-forum-optimized', false)) {
                            return (int) $tag->discussions()
                                ->whereVisibleTo($context->getActor())
                                ->count();
                        }

                        return (int) $tag->discussion_count;
                    }),
                // Unread indicator: true if the actor has unread discussions in this tag
                // (or, for a parent tag, in one of its children).
                Schema\Boolean::make('hasUnread')
                    ->get(function (Tag $tag, Context $context) {
                        $actor = $context->getActor();

                        if ($actor->isGuest()
                            || ! resolve(SettingsRepositoryInterface::class)->get('fof-categories.unread-enabled')) {
                            return false;
                        }

                        return in_array($tag->id, Unread::tagIdsFor($actor), true);
                    }),
            ]),

    (new Extend\ApiResource(UserResource::class))
            ->fields(fn () => [
                Schema\DateTime::make('joinTime')
                    ->property('joined_at'),
            ]),

    new Extend\Locales(__DIR__.'/resources/locale'),

    (new Extend\Event())
        ->subscribe(Listener\UpdateTagPostCount::class),

    (new Extend\Console())
        ->command(Console\RecountPostsCommand::class),
];
