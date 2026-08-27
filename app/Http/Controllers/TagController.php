<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Http\Resources\TagResource;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TagController extends Controller
{
    /**
     * Get all tags
     */
    public function index()
    {
        $tags = Tag::all();
        return TagResource::collection($tags);
    }

    /**
     * Create a new tag
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string|max:255',
        ]);

        $tag = Tag::withTrashed()->where('name', $validated['name'])->first();
        if ($tag && ! $tag->trashed()) {
            throw ValidationException::withMessages(['name' => ['business.task_label_exists']]);
        }
        if ($tag) {
            $tag->fill($validated);
            $tag->restore();
        } else {
            $tag = Tag::create($validated);
        }
        return new TagResource($tag);
    }

    /**
     * Update a tag
     */
    public function update(Request $request, Tag $tag)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:tags,name,'.$tag->id,
            'color' => 'sometimes|string|max:255',
        ]);

        $tag->update($validated);
        return new TagResource($tag);
    }

    /**
     * Delete a tag
     */
    public function destroy(Tag $tag)
    {
        $tag->tasks()->detach();
        $tag->delete();
        return response()->json(['message' => 'Tag deleted successfully']);
    }
}
