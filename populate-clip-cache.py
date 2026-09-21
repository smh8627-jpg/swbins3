"""
sd-webui (Stable Diffusion 1.x/2.x) needs the "openai/clip-vit-large-patch14" tokenizer
files from huggingface.co the first time it loads a checkpoint. On this network,
downloading them fails with an SSL certificate error (corporate proxy inspection), and
no IT firewall exception is available.

Workaround: get the 5 small files below from ANY device/network that isn't behind the
corporate proxy (phone hotspot, home PC, etc.), then run this script once to register
them into the local Hugging Face cache in the exact format huggingface_hub expects.
After that, sd-webui runs fully offline for this model (HF_HUB_OFFLINE=1 is set in
webui-user.bat) and never needs network access for it again.

Files to fetch and where to put them (any single folder, e.g. next to this script,
in a subfolder named "clip-vit-large-patch14"):
  https://huggingface.co/openai/clip-vit-large-patch14/resolve/main/config.json
  https://huggingface.co/openai/clip-vit-large-patch14/resolve/main/vocab.json
  https://huggingface.co/openai/clip-vit-large-patch14/resolve/main/merges.txt
  https://huggingface.co/openai/clip-vit-large-patch14/resolve/main/tokenizer_config.json
  https://huggingface.co/openai/clip-vit-large-patch14/resolve/main/special_tokens_map.json

Usage:
  python populate-clip-cache.py <folder containing the 5 files>
  (defaults to .\\clip-vit-large-patch14 next to this script if no argument given)
"""
import hashlib
import os
import shutil
import sys

REPO_ID = "openai/clip-vit-large-patch14"
REQUIRED_FILES = [
    "config.json",
    "vocab.json",
    "merges.txt",
    "tokenizer_config.json",
    "special_tokens_map.json",
]
FAKE_REVISION = "0" * 40  # local-only placeholder; never checked against the server


def hf_cache_dir():
    override = os.environ.get("HF_HUB_CACHE") or os.environ.get("HF_HOME")
    if override:
        return os.path.join(override, "hub") if os.environ.get("HF_HOME") else override
    return os.path.join(os.path.expanduser("~"), ".cache", "huggingface", "hub")


def main():
    src_dir = sys.argv[1] if len(sys.argv) > 1 else os.path.join(
        os.path.dirname(os.path.abspath(__file__)), "clip-vit-large-patch14"
    )

    missing = [f for f in REQUIRED_FILES if not os.path.isfile(os.path.join(src_dir, f))]
    if missing:
        print(f"Missing files in {src_dir}:")
        for f in missing:
            print(f"  - {f}")
        print("\nDownload them from huggingface.co/openai/clip-vit-large-patch14/resolve/main/")
        print("(from a network without the corporate proxy) and place them in that folder.")
        sys.exit(1)

    cache_root = hf_cache_dir()
    repo_dir = os.path.join(cache_root, "models--openai--clip-vit-large-patch14")
    snapshot_dir = os.path.join(repo_dir, "snapshots", FAKE_REVISION)
    blobs_dir = os.path.join(repo_dir, "blobs")
    refs_dir = os.path.join(repo_dir, "refs")

    os.makedirs(snapshot_dir, exist_ok=True)
    os.makedirs(blobs_dir, exist_ok=True)
    os.makedirs(refs_dir, exist_ok=True)

    with open(os.path.join(refs_dir, "main"), "w") as f:
        f.write(FAKE_REVISION)

    for filename in REQUIRED_FILES:
        src = os.path.join(src_dir, filename)
        with open(src, "rb") as f:
            data = f.read()
        blob_hash = hashlib.sha256(data).hexdigest()
        blob_path = os.path.join(blobs_dir, blob_hash)
        if not os.path.isfile(blob_path):
            shutil.copyfile(src, blob_path)
        dest = os.path.join(snapshot_dir, filename)
        if os.path.exists(dest):
            os.remove(dest)
        try:
            os.symlink(blob_path, dest)
        except OSError:
            shutil.copyfile(blob_path, dest)
        print(f"registered {filename}")

    print(f"\nDone. Cached at: {snapshot_dir}")
    print("sd-webui can now load this tokenizer with HF_HUB_OFFLINE=1 (already set in webui-user.bat).")


if __name__ == "__main__":
    main()
