var express = require("express");
var path = require('path');
var http = require('http');

// prepare a simple HTTP server
var app = express();
var server = http.createServer(app);
server.listen(3000);
console.log("Asterisk Click2call - http://0.0.0.0:3000 - Server Started");

app.get('/', function(req, res){
	res.send('Hello world!');
});