#!/usr/bin/python
# RTBF TV Series downloader.
#   by Christophe Vandeplas <christophe@vandeplas.com>
#
# This script will download the episodes of TV series form the RTBF.
# Only series that are available as 'revoir' will work of course.
# How is this working?
# 1. The XML feed with the latest episodes is fetched.
# 2. From that file the unique id is extracted.
# 3. That unique id is used to download the JSON file for that episode.
# 4. In that JSON file a full download url is available.
# 5. That file is downloaded and saved to the disk. Only if it was not yet on the disks
#
# This script is provided as-is without any warranty.
# You are free to use, modify and distribute this script with no limitations.
# However it'd be great to leave my credits and inform me of any changes you performed.
# Beer is also appreciated.

from datetime import date, timedelta
from urllib2 import urlopen, HTTPError
from xml.dom import minidom
import re
import json
import os.path

## Variables : 
# series - URL encoded form of the series. Usually simply the name of the 
#          series, lowercase and with _ instead of spaces or dashs.
#          This is only working for series that are available as 'revoir'.
## Variables to define the format of the final filename of the video on your disk
# series_short            - A custom short name of the series.
# series_episode_id_field - What field should be used as episode number. 
#                           Look at the JSON file to select the variable

# an example 
series = 'questions_d_argent'
series_short = 'questions_argent'   
series_episode_id_field = 'created'
# another example:
#series = 'une_brique_dans_le_ventre'
#series_short = 'ubdlv'  
#series_episode_id_field = 'created'
# a third example:
#series = 'plus_belle_la_vie'
#series_short = 'pblv'   
#series_episode_id_field = 'subtitle'

# Obvious
debug = False

# Only download new episodes that you already have on your disk
incremental_download = True


# Do not change anything below
xml_url = 'http://rss.rtbf.be/media/rss/programmes/'+series+'.xml'
videodetail_url = 'http://www.rtbf.be/api/media/video?method=getVideoDetail&args[]={0}'
file_ext = '.mp4'


def getText(nodelist):
    rc = []
    for node in nodelist:
        if node.nodeType == node.TEXT_NODE:
            rc.append(node.data)
    return ''.join(rc)
    
def getEpisodeIdsFromXmlList(url):
    # Extract episode Ids from the XML feed of the series
    # and return a list of ids
    if debug: print "getEpisodeIdsFromXmlList("+url+")"
    try:
        dom = minidom.parse(urlopen(url))
    except HTTPError: 
        print "ERROR: Please check your 'series' variable. Getting HTTP Error."
        exit(1)
        
    episode_ids = []
    for node in dom.getElementsByTagName('item'):
        link = getText(node.getElementsByTagName('link')[0].childNodes)
        id_reg = re.search('id=([0-9]+)', link)
        if id_reg:
            if debug: print "  id="+id_reg.group(1)
            episode_ids.append(id_reg.group(1))
    return episode_ids
    
def downloadEpisode(episode_id, episode_url):
    file_name_short = series_short  + '_' + episode_id + file_ext
    file_path = file_name_short
    # checks if file already exists
    if incremental_download and os.path.exists(file_path):
        print "Episode {0} already downloaded in the past.".format(episode_id)
        return

    # not downloaded yet, let's download it
    try:
        u = urlopen(episode_url)
        f = open(file_path,'wb')
        # progress-bar stuff, otherwise a simple u.read() will suffice
        meta = u.info()
        file_size = int(meta.getheaders("Content-Length")[0])
        print "Downloading: %s Bytes: %s" % (file_name_short, file_size)
        file_size_dl = 0
        block_sz = 8192
        while True:
            buffer = u.read(block_sz)
            if not buffer:
                break
        
            file_size_dl += block_sz
            f.write(buffer)
            status = r"%10d  [%3.2f%%]" % (file_size_dl, file_size_dl * 100. / file_size)
            status = status + chr(8)*(len(status)+1)
            print status,

        f.close()
        print ''
    
        print "Episode {0} downloaded successfully.".format(episode_id)
    except HTTPError:
        print "Episode {0} not downloaded.".format(episode_id)


print "Fetching episode IDs"
episode_ids = getEpisodeIdsFromXmlList(xml_url)

for episode_id in episode_ids:
    # fetch the json data for that episode 
    # This json contains the full url of our episode
    url = videodetail_url.format(episode_id)
    if debug: print url
    u = urlopen(url)
    json_data = json.load(u)
    if debug: print json_data
    episode_id = json_data['data'][series_episode_id_field]
    #    urlEncodedTitle
    if episode_id :
        if debug: print "Fetching episodeid "+str(episode_id)
        episode_id = str(episode_id).replace(' ', '_').lower() # clean filename from spaces
        episode_url = json_data['data']['urls']  
        downloadEpisode(episode_id, episode_url)
    else: 
        if debug: print "Not downloadable episodeid "+str(episode_id)
