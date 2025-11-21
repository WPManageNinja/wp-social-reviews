import ReviewsNotification from './reviews-notification.js';

jQuery(document).ready(function ($){
    "use strict";

    //render reviews notification template
    let notification_id = $('.wpsr-reviews-notification-card-wrapper').data('notification_id');
    if(notification_id){
        ReviewsNotification.init();
    }
});