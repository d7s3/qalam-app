<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Livewire\WithFileUploads;

/**
 * Adds a profile-picture upload/removal flow to a Livewire settings component.
 * Consuming components must implement avatarOwner() to say which user (and
 * therefore which guard) the picture belongs to.
 */
trait HasAvatarUpload
{
    use WithFileUploads;

    public $avatarFile;

    abstract protected function avatarOwner(): User;

    public function updatedAvatarFile(): void
    {
        $this->validate([
            'avatarFile' => 'required|image|max:5120',
        ], [
            'avatarFile.required' => 'يرجى اختيار صورة.',
            'avatarFile.image' => 'يجب أن يكون الملف صورة.',
            'avatarFile.max' => 'يجب ألا يتجاوز حجم الصورة 5 ميجابايت.',
        ]);

        $user = $this->avatarOwner();

        $manager = new ImageManager(new Driver);
        $image = $manager->decode($this->avatarFile->getRealPath());
        $image->cover(400, 400);

        $webpData = $image->encode(new WebpEncoder(80))->toString();
        $filename = 'avatars/'.$user->id.'_'.uniqid().'.webp';

        Storage::disk('public')->put($filename, $webpData);

        $oldPath = $user->avatar_path;
        $user->update(['avatar_path' => $filename]);

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        $this->avatarFile = null;
        $this->dispatch('profile-updated');
    }

    public function deleteAvatar(): void
    {
        $user = $this->avatarOwner();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        $this->dispatch('profile-updated');
    }
}
