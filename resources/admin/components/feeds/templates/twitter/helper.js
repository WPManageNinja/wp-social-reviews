function generateUserPermalink(str) {
    if (str) {
        return str.replace(/[@]+[A-Za-z0-9-_]+/g, function (name) {
            let username = name.replace("@", "");
            return '<a href="https://twitter.com/' + username + '" target="_blank">' + name + '</a>';
        });
    }
}

function getVideoUrl(media) {
    if (media.variants) {
        let variants = media.variants;
        for (let index = 0; index < variants.length; ++index) {
            let variant = variants[index];
            if (variant.content_type === 'video/mp4') {
                return variant.url;
            }
        }
    }
}

function mediaClass(media) {
    if(!media) return;
    let className = 'wpsr-feed-photo';
    if (media.type === 'animated_gif') {
        className = 'wpsr-feed-animated_gif';
    } else if(media.type === 'video') {
        className = 'wpsr-feed-video';
    }

    return className;
}

export {generateUserPermalink,getVideoUrl,mediaClass}