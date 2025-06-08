/*
Template Name: Color Admin - Responsive Admin Dashboard Template build with Bootstrap 5
Version: 5.5.0
Author: Sean Ngu
Website: http://www.seantheme.com/color-admin/
*/

var handleFormSummernote = function () {
	"use strict";
	$('#comment').summernote({
		placeholder: 'Leave a comment here...',
		height: 300
	});
};

var ForumDetailsPage = function () {
	"use strict";
	return {
		//main function
		init: function () {
			handleFormSummernote();
		}
	};
}();

$(document).ready(function() {
	ForumDetailsPage.init();
});