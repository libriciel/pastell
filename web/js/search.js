// Pour que l'accordéon se replie après la recherche

$(document).ready(function() {
  $.urlParamExists = function (name) {
      var results = new RegExp('[\?&]' + name + '=([^&#]*)')
                        .exec(window.location.search);

      return results !== null;
  }

  $.urlParam = function(name){
    var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
    if (results==null) {
       return null;
    }
    return decodeURI(results[1]) || 0;
}

$("#headingOne").addClass("collapsed");
$("#collapseOne").removeClass("show");
$("#title-result").addClass("ls-on");
$("#title-result").removeClass("ls-off")

});
