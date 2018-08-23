(function( $ ) {
var subscribeBtn = $('#cgi-notification-subscribe');
var buttonText = subscribeBtn.find('input').val();
var postId = subscribeBtn.find('input').data('postid');
var oneSignalPostId = "post-" + postId;
var bodyClassList = document.getElementsByTagName('body')[0].classList;
var postIdInBodyClass = "";
 

var subscribed;
 if (subscribeBtn.hasClass('subscribed')) {
  subscribed = true;
 } else {
  subscribed = false;
 } 

 function addNotificationToDB() {
    $.ajax({
      url : CGI_Ajax.ajaxurl,
      type : 'post',
      data : {
        action : 'add_notification',
        data   : postId
      },
      success : function( response ) {
        document.getElementById('post-data').setAttribute("value", "Unsubscribe from this Post");
        return subscribed = true;
      }
    });  
 }

 function removeNotificationFromDB() {
    $.ajax({
      url : CGI_Ajax.ajaxurl,
      type : 'post',
      data : {
        action : 'remove_notification',
        data   : postId
      },
      success : function( response ) {
        // buttonText = 'Subscribe to this Post';
        document.getElementById('post-data').setAttribute("value", "Subscribe to this Post");
        return subscribed = false;
      }
    });
 }


  function checkUserId() {
    OneSignal.push(function() {
      OneSignal.getUserId(function(playerId) {
        console.log("Current OneSignal User ID on load:", playerId);
        $.ajax({
          type: 'post',
          data: {
            action: 'save_player_id',
            data: playerId
          },
          success: function( response ) {
            // if new player id, send all tags here.
          }
        })
      });        
    });
  }




function oneSignalIsSubscribed() {
   OneSignal.push(function() {
    
    OneSignal.getTags(function(tags) {
      // All the tags stored on the current webpage visitor
      var oneSignalIsSubscribed = false;
      Object.keys(tags).map(function(item){
        if (item.substring(5) == postId) {
          oneSignalIsSubscribed = true;
        }
      });
      
    });
    return oneSignalIsSubscribed;
  });
}


subscribeBtn.on('submit', function(e) {
  e.preventDefault();

  if (subscribed) {
    OneSignal.push(function() {           
      OneSignal.deleteTag(oneSignalPostId).then(function(tagsSent) {
      });  
    });

    removeNotificationFromDB();
  } else {
    OneSignal.push(function() {           
      OneSignal.sendTag(oneSignalPostId, "subscribed").then(function(tagsSent) {
      });  
    });

    addNotificationToDB();  
  }
});

  window.addEventListener("load", checkUserId);
})( jQuery );
